<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CounterSaleService;
use App\Services\StockService;
use App\Services\TillService;
use App\Support\PosOperator;
use App\Support\Role;
use App\Support\ShopPolicy;
use App\Support\StockMode;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The counter (design §15.3): find pieces, ring up, take tenders, void, print. */
class SaleController extends Controller
{
    public function __construct(private CounterSaleService $sales, private TillService $tills)
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

    private function counterSale(CustomOrder $order): CustomOrder
    {
        abort_unless($order->channel === 'counter', 404);

        return $order;
    }

    /** Search by name, SKU or barcode; a scanned code picks its size. Stock is what this shop has. */
    public function catalogue(Request $request, StockService $stock)
    {
        $q = trim((string) $request->query('q', ''));
        $shop = Location::query()->online()->first();
        $exact = $q !== '' ? ProductVariant::where('active', true)->where(fn ($w) => $w->where('sku', $q)->orWhere('barcode', $q))->first() : null;
        $products = Product::where('in_stock', true)->with(['variants' => fn ($v) => $v->where('active', true)->orderBy('sort')])
            ->when($exact, fn ($p) => $p->whereKey($exact->product_id))
            ->when(! $exact && $q !== '', fn ($p) => $p->where('name', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%'))
            ->orderBy('name')->limit(60)->get();
        $v2 = StockMode::v2();

        return response()->json([
            'exact' => $exact ? ['product_id' => $exact->product_id, 'variant_id' => $exact->id] : null,
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id, 'name' => $p->name, 'price' => (int) round((float) $p->price), 'image_url' => $p->image_url, 'garment_type' => $p->garment_type,
                'sizes' => $p->variants->map(fn (ProductVariant $v) => ['id' => $v->id, 'part' => $v->part, 'size' => $v->size, 'sku' => $v->sku, 'barcode' => $v->barcode,
                    // Old model: one pool per product, shown on each size.
                    'available' => $v2 ? $stock->availableAt($v->setRelation('product', $p), $shop) : $p->availableStock()])->values()->all(),
            ])->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.variant_ids' => ['nullable', 'array', 'max:2'],
            'items.*.variant_ids.*' => ['integer'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'discount_reason' => ['required_with:discount', 'nullable', 'string', 'max:255'],
            'approval_pin' => ['nullable', 'string'],
        ], ['discount_reason.required_with' => 'Say why the discount is given.']);
        $seller = $this->operator($request);
        $discount = (int) ($data['discount'] ?? 0);
        if ($discount > 0 && trim((string) ($data['discount_reason'] ?? '')) === '') {
            throw ValidationException::withMessages(['discount_reason' => 'Say why the discount is given.']);
        }

        $approver = null;
        if ($discount > 0) {
            $subtotal = (int) Product::whereIn('id', collect($data['items'])->pluck('id'))->get()->keyBy('id')
                ->pipe(fn ($ps) => collect($data['items'])->sum(fn ($i) => (int) round((float) $ps[$i['id']]->price) * (int) $i['quantity']));
            $limit = ShopPolicy::cashierDiscountLimit($subtotal);
            if ($discount > $limit && ! Role::allows($seller->role, 'pos.approve')) {
                if (empty($data['approval_pin'])) {
                    throw ValidationException::withMessages(['approval_pin' => 'A discount over KSh ' . number_format($limit) . ' needs a manager’s PIN.']);
                }
                $approver = PosOperator::approver($data['approval_pin']);
            }
        }

        $order = $this->sales->start($this->till($request), $seller, $data['items'], $data['customer'] ?? [], $discount, $data['discount_reason'] ?? null, $approver);

        return response()->json(['sale' => $this->sales->present($order)]);
    }

    public function show(CustomOrder $order)
    {
        return response()->json(['sale' => $this->sales->present($this->counterSale($order))]);
    }

    public function tender(Request $request, CustomOrder $order)
    {
        $this->counterSale($order);
        $data = $request->validate([
            'method' => ['required', 'in:cash,mpesa_manual,mpesa_prompt'],
            'amount' => ['required', 'integer', 'min:1'],
            'tendered' => ['required_if:method,cash', 'nullable', 'integer', 'min:1'],
            'reference' => ['required_if:method,mpesa_manual', 'nullable', 'string', 'max:100'],
            'phone' => ['required_if:method,mpesa_prompt', 'nullable', 'string', 'max:30'],
        ], ['reference.required_if' => 'Type the M-Pesa code from the customer’s message.', 'phone.required_if' => 'Enter the customer’s M-Pesa number.']);
        $by = $this->operator($request);
        $till = $this->till($request);
        $out = [];

        match ($data['method']) {
            'cash' => $out = $this->sales->tenderCash($order, $till, $by, (int) $data['amount'], (int) $data['tendered']),
            'mpesa_manual' => $this->sales->tenderMpesaCode($order, $till, $by, (int) $data['amount'], $data['reference']),
            'mpesa_prompt' => $out = ['prompt' => ['status' => 'sent', 'attempt' => $this->sales->tenderMpesaPrompt($order, $by, (int) $data['amount'], $data['phone'])->id]],
        };

        return response()->json($out + ['sale' => $this->sales->present($order)]);
    }

    public function void(Request $request, CustomOrder $order)
    {
        $this->counterSale($order);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'approval_pin' => ['nullable', 'string']]);
        $by = $this->operator($request);
        $approver = null;
        if ((int) $order->sold_by !== $by->id && ! Role::allows($by->role, 'pos.approve')) {
            if (empty($data['approval_pin'])) {
                throw ValidationException::withMessages(['approval_pin' => 'Voiding someone else’s sale needs a manager’s PIN.']);
            }
            $approver = PosOperator::approver($data['approval_pin']);
        }
        $this->sales->void($order, $by, $data['reason'], $approver);

        return response()->json(['sale' => $this->sales->present($order)]);
    }

    /** The 80mm receipt (browser print). */
    public function receipt(CustomOrder $order)
    {
        $this->counterSale($order);
        $cash = CashMovement::where('custom_order_id', $order->id)->where('type', CashMovement::SALE)->get()->keyBy('order_payment_id');

        return view('pos.receipt', [
            'order' => $order,
            'lines' => $order->lines()->orderBy('id')->get(),
            'payments' => $order->payments()->orderBy('id')->get()->map(fn ($p) => ['payment' => $p, 'tendered' => $cash[$p->id]->tendered ?? null]),
            'seller' => \App\Models\User::find($order->sold_by)?->name,
            'footer' => ShopPolicy::receiptFooter(),
            'storeName' => \App\Support\StoreProfile::name(),
            'address' => \App\Support\StoreProfile::address(),
            'date' => $order->created_at->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i'),
        ]);
    }
}
