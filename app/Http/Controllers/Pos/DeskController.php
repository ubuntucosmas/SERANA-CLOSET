<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Services\ImageOptimizer;
use App\Services\ShopDeskService;
use App\Services\TillService;
use App\Support\Phone;
use App\Support\PosOperator;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The orders desk at the till (design §15.5): click & collect, bespoke intake, late entry from a paper slip. */
class DeskController extends Controller
{
    public function __construct(private ShopDeskService $desk, private TillService $tills)
    {
    }

    private function operator(Request $request)
    {
        return $request->attributes->get('pos_operator');
    }

    private function till(Request $request)
    {
        return $this->tills->current(null, $request->input('register', $request->query('register', 'Till 1')));
    }

    /** Reference and phone together: either alone finds nothing. */
    public function find(Request $request)
    {
        $data = $request->validate(['ref' => ['required', 'string', 'max:40'], 'phone' => ['required', 'string', 'max:30']]);
        $order = $this->desk->findForCollection($data['ref'], $data['phone']);
        abort_unless($order, 404, 'No order with that reference and phone number.');

        return response()->json(['order' => $this->desk->present($order)]);
    }

    /** Staff-only recovery: reference, customer name or phone; never a public lookup. */
    public function search(Request $request)
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:120']]);
        $q = trim($data['q']);
        if (mb_strlen($q) < 3) throw ValidationException::withMessages(['q' => 'Enter at least three characters.']);
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $phone = Phone::normalize($q);
        $orders = CustomOrder::where(function ($query) use ($q, $like, $phone) {
            $query->where('cipher', strtoupper($q))->orWhere('full_name', 'like', $like);
            if (strlen($phone) >= 9) $query->orWhere('phone', $phone)->orWhere('whatsapp_number', $phone);
        })->latest('id')->limit(21)->get();
        return response()->json([
            'has_more' => $orders->count() > 20,
            'orders' => $orders->take(20)->map(fn ($order) => [
                'id' => $order->id, 'reference' => $order->cipher, 'customer' => $order->full_name,
                'kind' => $order->kind, 'balance_due' => max(0, (int) $order->balance_due),
                'state' => $order->order_state, 'fulfilment' => $order->fulfilment_status,
                'placed_at' => $order->created_at->timezone(config('app.display_timezone'))->format('d M Y H:i'),
            ])->values(),
        ]);
    }

    public function show(CustomOrder $order)
    {
        return response()->json(['order' => $this->desk->present($order)]);
    }

    public function collect(Request $request, CustomOrder $order)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->desk->collect($order, $this->operator($request), $data['phone'], $data['note'] ?? null, $this->till($request));

        return response()->json(['order' => $this->desk->present($order)]);
    }

    public function intake(Request $request, ImageOptimizer $images)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            // Staff may name any garment; never one of the words that mean a shop or drop order.
            'garment' => ['nullable', 'string', 'max:100', Rule::notIn(['standard', 'drop'])],
            'fabric_preference' => ['nullable', 'string', 'max:255'],
            'selected_color' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'bust_cm' => ['nullable', 'numeric', 'between:30,250'],
            'waist_cm' => ['nullable', 'numeric', 'between:30,250'],
            'hips_cm' => ['nullable', 'numeric', 'between:30,250'],
            'length_cm' => ['nullable', 'numeric', 'between:10,250'],
            'height_cm' => ['nullable', 'numeric', 'between:50,250'],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'occasion' => ['nullable', 'string', 'max:100'],
            'selected_fit' => ['nullable', 'string', 'max:50'],
            'quote' => ['nullable', 'integer', 'min:1'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
        ], ['images.max' => 'Attach up to 6 reference images.', 'images.*.mimes' => 'Reference images must be JPG, PNG, WEBP or PDF.']);
        if (! Phone::isValidKenyanMobile(Phone::normalize($data['phone']))) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Kenyan mobile number, e.g. 07XX XXX XXX.']);
        }
        $paths = array_map(fn ($file) => $images->store($file, 'inspiration_images'), $request->file('images', []));
        $order = $this->desk->intake($this->operator($request), $data, $paths);

        return response()->json(['order' => $this->desk->present($order)]);
    }

    public function lateEntry(Request $request)
    {
        $data = $request->validate([
            'slip_number' => ['required', 'string', 'max:50'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now', 'after:' . now()->subDays(7)->toDateTimeString()],
            'reason' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.variant_ids' => ['nullable', 'array', 'max:2'],
            'items.*.variant_ids.*' => ['integer'],
            'payments' => ['required', 'array', 'min:1', 'max:6'],
            'payments.*.method' => ['required', 'in:cash,mpesa_manual'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.reference' => ['required_if:payments.*.method,mpesa_manual', 'nullable', 'string', 'max:100'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'approval_pin' => ['nullable', 'string'],
        ], [
            'occurred_at.before_or_equal' => 'The slip’s time can’t be in the future.',
            'occurred_at.after' => 'Slips older than a week go to the owner, not the till.',
            'reason.required' => 'Say why the sale was written on paper.',
            'payments.*.reference.required_if' => 'Type the M-Pesa code written on the slip.',
        ]);
        $by = $this->operator($request);
        $approver = $by;
        if (! Role::allows($by->role, 'pos.approve')) {
            if (empty($data['approval_pin'])) {
                throw ValidationException::withMessages(['approval_pin' => 'A late entry needs a manager’s PIN.']);
            }
            $approver = PosOperator::approver($data['approval_pin']);
        }
        $at = Carbon::parse($data['occurred_at'])->setTimezone(config('app.timezone'));
        $order = $this->desk->lateEntry($this->till($request), $by, $approver, $data['slip_number'], $at, $data['reason'],
            $data['items'], $data['payments'], $data['customer'] ?? []);

        return response()->json(['sale' => app(\App\Services\CounterSaleService::class)->present($order)]);
    }
}
