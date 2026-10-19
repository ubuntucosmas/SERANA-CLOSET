<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\OrderProgressSnap;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

use App\Models\Product;
use App\Models\Category;
use App\Models\GalleryImage;

use App\Models\Lead;
use App\Models\ThemeSetting;
use App\Models\Drop;
use App\Support\OrderStatus;
use App\Support\StudioWorkflow;
use App\Support\PaymentSettings;
use App\Services\OrderSettlementService;
use Carbon\Carbon;

class StudioController extends Controller
{
    public function index(Request $request)
    {
        $weekAgo = Carbon::now()->subDays(7);

        // Production stages still being actively worked (not yet completed/shipped).
        $activeStages = array_values(array_diff(
            OrderStatus::PRODUCTION_STAGES,
            OrderStatus::PRODUCTION_TERMINAL
        ));

        // Lightweight, always-present payload for the Home screen.
        $stats = [
            // Abandoned checkouts (expired) and cancelled orders are not demand.
            'new_orders_week'   => CustomOrder::where('created_at', '>=', $weekAgo)
                                        ->whereNotIn('status', [OrderStatus::EXPIRED, OrderStatus::CANCELLED])->count(),
            // Money received in the last 7 days (archived orders still count).
            'revenue_week'      => (float) CustomOrder::withTrashed()->where('is_paid', true)
                                        ->where('paid_at', '>=', $weekAgo)->sum('price_quoted'),
            // Being made right now; new briefs awaiting a quote are not "in production".
            'in_production'     => CustomOrder::whereIn('status', array_values(array_diff($activeStages, ['pending'])))->count(),
            'subscribers_total' => Lead::count(),
        ];

        // One availability figure (sold + unpaid holds) for the stock alert, the
        // products table and the storefront, so they never disagree.
        $products = Product::with('category')->withAvailability()->latest()->get()->append('available_stock');

        $workflow = [];
        foreach (StudioWorkflow::QUEUES as $queue) {
            $workflow[$queue] = StudioWorkflow::apply(CustomOrder::query(), $queue)->count();
        }

        $attention = [
            'awaiting' => CustomOrder::where(function ($query) {
                $query->whereIn('status', [OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH, OrderStatus::PAID, 'pending']);
            })->latest()->take(12)->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'full_name' => $o->full_name,
                    'amount' => $o->price_quoted,
                    'reference' => $o->cipher,
                    'created_at' => $o->created_at,
                    'status' => $o->status,
                    'is_paid' => $o->is_paid,
                    'next_action' => $o->is_paid ? 'Ready for fulfilment'
                        : ($o->payment_method === PaymentSettings::WHATSAPP && $o->status === OrderStatus::PENDING_PAYMENT ? 'WhatsApp order: confirm payment'
                        : ($o->status === OrderStatus::AMOUNT_MISMATCH ? 'Review payment amount'
                        : ($o->status === OrderStatus::PAYMENT_FAILED ? 'Follow up on failed payment'
                        : ($o->status === 'pending' ? 'Review custom order' : 'Awaiting payment confirmation')))),
                ]),
            // Switched off by staff, or a limited batch with two or fewer units a
            // shopper can still buy (unpaid holds count as taken).
            'low_stock' => $products
                ->filter(fn ($p) => ! $p->in_stock || ($p->available_stock !== null && $p->available_stock <= 2))
                ->take(8)->values()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name,
                    'note' => ! $p->in_stock ? 'Marked out of stock'
                        : ($p->available_stock === 0 ? 'Sold out' : $p->available_stock . ' left')
                        . ($p->reservedQuantity() ? ' · ' . $p->reservedQuantity() . ' on hold awaiting payment' : '')]),
        ];

        // A customer who paid twice is owed a refund; surface it until handled.
        $duplicatePaid = PaymentAttempt::where('status', PaymentAttempt::PAID)
            ->selectRaw('custom_order_id, COUNT(*) as paid_count')->groupBy('custom_order_id')
            ->havingRaw('COUNT(*) > 1')->pluck('paid_count', 'custom_order_id');
        if ($duplicatePaid->isNotEmpty()) {
            $refunds = CustomOrder::withTrashed()->whereIn('id', $duplicatePaid->keys())->get()->map(fn ($o) => [
                'id' => $o->id, 'full_name' => $o->full_name, 'amount' => $o->price_quoted, 'reference' => $o->cipher,
                'next_action' => 'Paid ' . $duplicatePaid[$o->id] . ' times: refund owed',
            ]);
            $attention['awaiting'] = $refunds->concat($attention['awaiting'])->values();
        }

        return Inertia::render('Admin/StudioDashboard', [
            'stats'      => $stats,
            'workflow' => $workflow,
            'generated_at' => now()->toIso8601String(),
            'attention'  => $attention,
            'active_tab' => $request->get('tab', 'home'),

            // Eager so every list stays in sync after a save/redirect. The dataset
            // is boutique-sized; paginate here if a section ever grows large.
            // One page at a time, filtered and searched on the server: sending every
            // order made the page 5.7MB at 3,000 orders. Lazy, so partial reloads
            // (search, Load more) compute only what was asked for.
            'orders'            => fn () => $this->orderPage($request),
            // An order opened from a Home shortcut or a link, wherever it sits in the list.
            'focus_order'       => fn () => $request->filled('order')
                ? CustomOrder::withTrashed()->with(['user', 'progressSnaps'])->find((int) $request->input('order'))
                : null,
            'products'          => $products,
            'archived_products' => Product::onlyTrashed()->with('category')->latest()->get(),
            'categories'        => Category::all(),
            'gallery'           => GalleryImage::latest()->get(),
            'archived_gallery'  => GalleryImage::onlyTrashed()->latest()->get(),
            'leads'             => Lead::latest()->get(['id', 'email', 'created_at']),
            'custom_order_options' => \App\Support\CustomOrderOptions::all(),
            'payment_settings'  => PaymentSettings::forStudio(),
            'drops'             => Drop::with(['reservations' => fn ($q) => $q->latest()])
                                        ->withCount('paidReservations')->latest()->get(),
        ]);
    }

    public function update(Request $request, CustomOrder $order)
    {
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(array_merge(OrderStatus::PRODUCTION_STAGES, [OrderStatus::PENDING_PAYMENT, OrderStatus::PAID, OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH, OrderStatus::EXPIRED]))],
            'internal_notes' => 'nullable|string',
            'price_quoted' => 'nullable|numeric|min:0',
            'is_paid' => 'nullable|boolean',
        ]);

        if (array_key_exists('is_paid', $validated) && (bool) $validated['is_paid'] !== $order->is_paid) {
            throw \Illuminate\Validation\ValidationException::withMessages(['is_paid' => 'Payment is confirmed by the payment service and cannot be changed here.']);
        }
        $commerce = in_array($order->type, ['standard', 'drop'], true);
        if (($order->is_paid || $commerce) && array_key_exists('price_quoted', $validated) && (float) $validated['price_quoted'] !== (float) $order->price_quoted) {
            throw \Illuminate\Validation\ValidationException::withMessages(['price_quoted' => 'A checkout or paid order’s total cannot be changed.']);
        }
        if ($validated['status'] !== $order->status && ! in_array($validated['status'], OrderStatus::PRODUCTION_STAGES, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Payment states are managed automatically. Choose a fulfilment stage.']);
        }
        if ($commerce && $validated['status'] !== $order->status) {
            if (! $order->is_paid || ! in_array($validated['status'], OrderStatus::PRODUCTION_STAGES, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Confirm payment before updating fulfilment. Payment states are managed automatically.']);
            }
        }
        unset($validated['is_paid']);
        \App\Support\Audit::changes('order.updated', $order, $validated);
        $order->update($validated + \App\Support\LegacyOrderMap::forStatus($validated['status']));

        return back()->with('success', 'Order updated successfully.');
    }

    /**
     * Bespoke work is quoted and paid over WhatsApp, outside the M-Pesa STK
     * flow. Staff record that payment here with the amount and a reference so
     * the customer's tracking and the Studio show it as paid. Shop orders and
     * Drop reservations can only be confirmed by M-Pesa settlement.
     */
    /** Studio order list: ?o_q= search, ?o_filter=all|custom|shop, ?o_archived=1, ?o_page=N. */
    private function orderPage(Request $request): array
    {
        $q = trim((string) $request->input('o_q', ''));
        $filter = $request->input('o_filter', 'all');

        $page = StudioWorkflow::apply(CustomOrder::query(), $request->input('o_queue'))
            ->when($request->boolean('o_archived'), fn ($query) => $query->onlyTrashed())
            ->when($filter === 'shop', fn ($query) => $query->where('type', 'standard'))
            ->when($filter === 'custom', fn ($query) => $query->where('type', '!=', 'standard'))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
                $digits = preg_replace('/\D/', '', $q);
                $query->where(function ($w) use ($q, $like, $digits) {
                    $w->where('full_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('cipher', 'like', $like)
                        ->orWhere('items_json', 'like', $like);
                    if (strlen($digits) >= 4) {
                        // Phones are stored as 2547…; match the last digits however they were typed.
                        $w->orWhere('phone', 'like', '%' . substr($digits, -9) . '%')
                            ->orWhere('whatsapp_number', 'like', '%' . substr($digits, -9) . '%');
                    }
                    if (ctype_digit($q)) $w->orWhere('id', (int) $q);
                });
            })
            ->with(['user', 'progressSnaps'])
            ->when($request->input('o_sort') === 'oldest', fn ($query) => $query->oldest(), fn ($query) => $query->latest())
            ->paginate(30, ['*'], 'o_page', max(1, (int) $request->input('o_page', 1)));

        return [
            'data' => $page->items(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'archived' => $request->boolean('o_archived'),
            'queue' => in_array($request->input('o_queue'), StudioWorkflow::QUEUES, true) ? $request->input('o_queue') : 'all',
            'sort' => $request->input('o_sort') === 'oldest' ? 'oldest' : 'newest',
            'query' => $q,
            'filter' => in_array($filter, ['shop', 'custom'], true) ? $filter : 'all',
        ];
    }

    public function recordPayment(Request $request, CustomOrder $order)
    {
        if ($order->type === 'standard' && $order->payment_method === PaymentSettings::WHATSAPP) {
            return $this->confirmWhatsAppPayment($request, $order);
        }
        if (in_array($order->type, ['standard', 'drop'], true)) {
            return back()->with('error', 'Shop orders and Drop reservations are confirmed automatically by M-Pesa.');
        }
        if ($order->is_paid) {
            return back()->with('error', 'This order is already marked as paid.');
        }
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:100'],
        ], [
            'amount.integer' => 'Enter the amount in whole shillings.',
            'reference.required' => 'Add the M-Pesa code or a short note about how this was paid.',
        ]);

        $note = sprintf('[%s] Payment recorded by %s: KSh %s, ref %s',
            now()->timezone(config('app.display_timezone'))->format('Y-m-d H:i'), $request->user()->name,
            number_format($data['amount']), $data['reference']);

        $claimed = CustomOrder::whereKey($order->id)->where('is_paid', false)->update([
            'is_paid' => true,
            'paid_at' => now(),
            'price_quoted' => $data['amount'],
            'mpesa_receipt_number' => mb_substr($data['reference'], 0, 100),
            'internal_notes' => trim(($order->internal_notes ? $order->internal_notes . "\n" : '') . $note),
        ]);
        if ($claimed) {
            \App\Support\Audit::record('payment.recorded', $order, [], ['amount' => (int) $data['amount'], 'reference' => $data['reference']]);
        }

        return back()->with($claimed ? 'success' : 'error', $claimed ? 'Payment recorded.' : 'This order is already marked as paid.');
    }

    /**
     * A WhatsApp shop order is paid outside M-Pesa STK (Send Money, Pochi, cash
     * on delivery…). Confirming it runs the same settlement as an M-Pesa
     * callback: paid_at, stock committed once, receipt email, tracking.
     */
    private function confirmWhatsAppPayment(Request $request, CustomOrder $order)
    {
        if ($order->is_paid) {
            return back()->with('error', 'This order is already marked as paid.');
        }
        if (in_array($order->status, [OrderStatus::CANCELLED], true)) {
            return back()->with('error', 'This order was cancelled. Ask the customer to order again.');
        }
        $total = (int) round((float) $order->price_quoted);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:100'],
        ], [
            'amount.integer' => 'Enter the amount in whole shillings.',
            'reference.required' => 'Add the M-Pesa code or a short note about how this was paid.',
        ]);
        if ((int) $data['amount'] !== $total) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'This order’s total is KSh ' . number_format($total) . '. Confirm once the full amount has been received.',
            ]);
        }

        // Re-claim stock if the hold lapsed; money already received is recorded either way.
        $stockOk = $order->secureStockForPayment();
        $order->update(['internal_notes' => trim(($order->internal_notes ? $order->internal_notes . "\n" : '') . sprintf(
            '[%s] WhatsApp payment confirmed by %s: KSh %s, ref %s',
            now()->timezone(config('app.display_timezone'))->format('Y-m-d H:i'), $request->user()->name,
            number_format($total), $data['reference']))]);
        $settled = app(OrderSettlementService::class)->markPaid($order, mb_substr($data['reference'], 0, 100));
        if ($settled) {
            \App\Support\Audit::record('payment.recorded', $order, [], ['amount' => $total, 'reference' => $data['reference'], 'channel' => 'whatsapp']);
        }

        if (! $settled) {
            return back()->with('error', 'This order is already marked as paid.');
        }

        return back()->with($stockOk ? 'success' : 'error', $stockOk
            ? 'Payment confirmed. The customer’s receipt is on its way.'
            : 'Payment confirmed, but an item’s hold had lapsed and it has since sold. Check availability before packing.');
    }

    /** Cancel an unpaid WhatsApp order: stock goes back on sale. */
    public function cancelOrder(CustomOrder $order)
    {
        if ($order->is_paid || $order->payment_method !== PaymentSettings::WHATSAPP || $order->status !== OrderStatus::PENDING_PAYMENT) {
            return back()->with('error', 'Only unpaid WhatsApp orders can be cancelled here.');
        }
        $order->releaseReservations();
        CustomOrder::whereKey($order->id)->where('is_paid', false)->update(\App\Support\LegacyOrderMap::forStatus(OrderStatus::CANCELLED));
        \App\Support\Audit::record('order.cancelled', $order, ['status' => $order->status], ['status' => OrderStatus::CANCELLED]);

        return back()->with('success', 'Order cancelled. Its stock is back on sale.');
    }

    public function destroy(CustomOrder $order)
    {
        if ($order->paymentAttempts()->where('status', PaymentAttempt::PENDING)->exists()) {
            return back()->with('error', 'This order has an M-Pesa payment still in progress. Archive it after the payment succeeds, fails or expires.');
        }
        if ($order->is_paid && ! in_array($order->status, OrderStatus::PRODUCTION_TERMINAL, true)) {
            return back()->with('error', 'Paid orders can be archived once they are completed or dispatched.');
        }

        $order->delete();
        \App\Support\Audit::record('order.archived', $order);
        return back()->with('success', 'Order moved to the archive.');
    }

    public function restore($id)
    {
        $order = CustomOrder::withTrashed()->findOrFail($id);
        $order->restore();
        \App\Support\Audit::record('order.restored', $order);
        return back()->with('success', 'Order restored to active registry.');
    }

    public function forceDelete($id)
    {
        $order = CustomOrder::withTrashed()->findOrFail($id);

        // Payment records are financial evidence: an order with any payment
        // history (or that was paid) is kept in the archive, never purged.
        if ($order->is_paid || $order->paymentAttempts()->exists()) {
            return back()->with('error', 'Orders with payment history cannot be permanently deleted. They stay in the archive as a record.');
        }

        // Clean up linked progress snaps imagery if they exist
        foreach ($order->progressSnaps as $snap) {
            if ($snap->image_path) {
                Storage::disk(config('filesystems.public_disk'))->delete($snap->image_path);
            }
        }
        
        \App\Support\Audit::record('order.purged', $order, $order->only(['cipher', 'full_name', 'type', 'status', 'price_quoted']));
        $order->forceDelete();
        return back()->with('success', 'Order and associated visual assets permanently purged.');
    }

    public function storeProgressSnap(Request $request, CustomOrder $order)
    {
        $validated = $request->validate([
            'image' => 'required|image|max:10240',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            // Store optimized raw path; models resolve to full URL via dynamic disk logic.
            $validated['image_path'] = app(\App\Services\ImageOptimizer::class)
                ->store($request->file('image'), 'progress_snaps');
            $validated['custom_order_id'] = $order->id;
        }

        OrderProgressSnap::create($validated);

        return back()->with('success', 'Order photo uploaded.');
    }
}
