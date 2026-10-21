<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\OrderProgressSnap;
use App\Models\PaymentAttempt;
use App\Models\OrderPayment;
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
    /** What an order carries to the Studio, from the list or a link (?order=): one list, so the two never differ. */
    private const ORDER_APPENDS = ['pay_link', 'pay_whatsapp_url', 'ledger_rows', 'stock_problem', 'seller_name', 'delivery_info'];

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
                                        ->whereNot(fn ($q) => $q->withLegacyStatus([OrderStatus::EXPIRED, OrderStatus::CANCELLED]))->count(),
            // Money received in the last 7 days (archived orders still count).
            'revenue_week'      => (float) CustomOrder::withTrashed()->paidUp()
                                        ->where('paid_at', '>=', $weekAgo)->sum(CustomOrder::totalColumn()),
            // Being made right now; new briefs awaiting a quote are not "in production".
            'in_production'     => CustomOrder::withLegacyStatus(array_values(array_diff($activeStages, ['pending'])))->count(),
            'subscribers_total' => Lead::count(),
        ];

        // One availability figure (sold + unpaid holds) for the stock alert, the
        // products table and the storefront, so they never disagree.
        $products = Product::with('category')
            ->when($request->get('tab') === 'products', fn ($query) => $query->with('variants:id,product_id,sku,size,part,active'))
            ->withAvailability()->latest()->get();
        if (\App\Support\StockMode::v2()) {
            app(\App\Services\StockService::class)->preloadAvailability($products);
        }
        $products = $products->append('available_stock');

        $workflow = [];
        foreach (StudioWorkflow::QUEUES as $queue) {
            $workflow[$queue] = StudioWorkflow::apply(CustomOrder::query(), $queue)->count();
        }

        $attention = [
            // A sale still at the counter is the till's, not Studio work.
            'awaiting' => CustomOrder::where('channel', '!=', 'counter')->where(function ($query) {
                $query->withLegacyStatus([OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH, OrderStatus::PAID, 'pending']);
            })->latest()->take(12)->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'full_name' => $o->full_name,
                    'amount' => $o->total(),
                    'reference' => $o->cipher,
                    'created_at' => $o->created_at,
                    'status' => $o->legacyStatus(),
                    'is_paid' => $o->isPaidUp(),
                    'next_action' => $o->isPaidUp() ? 'Ready for fulfilment'
                        : ($o->payment_method === PaymentSettings::WHATSAPP && $o->legacyStatus() === OrderStatus::PENDING_PAYMENT ? 'WhatsApp order: confirm payment'
                        : ($o->hasAmountMismatch() ? 'Review payment amount'
                        : ($o->legacyStatus() === OrderStatus::PAYMENT_FAILED ? 'Follow up on failed payment'
                        : ($o->legacyStatus() === 'pending' ? 'Review custom order' : 'Awaiting payment confirmation')))),
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

        // Phase E: a drawer closed in the last week with a difference over the alert.
        $attention['till_variances'] = $request->user()->can('till.review') ? \App\Models\TillSession::where('status', 'closed')
            ->where('closed_at', '>=', now()->subDays(7))->whereRaw('ABS(variance) > ?', [\App\Support\ShopPolicy::tillVarianceAlert()])
            ->with('closedBy:id,name')->latest('closed_at')->take(5)->get()->map(fn ($t) => ['id' => $t->id, 'register' => $t->register, 'variance' => (int) $t->variance,
                'closed_by' => $t->closedBy?->name, 'closed_at' => $t->closed_at?->copy()->timezone(config('app.display_timezone'))->format('d M, H:i'), 'reason' => $t->variance_reason])->values()->all() : [];

        // Phase C: every payment problem, oldest first, with the count for the dashboard.
        $attention['payments'] = $this->paymentAttention();
        $attention['payments_count'] = count($attention['payments']);

        return Inertia::render('Admin/StudioDashboard', [
            'stats'      => $stats,
            'workflow' => $workflow,
            'generated_at' => now()->toIso8601String(),
            'attention'  => $attention,
            'active_tab' => $request->get('tab', 'home'),

            // Load unrelated archives, content and customer lists only in their workspaces.
            // One page at a time, filtered and searched on the server: sending every
            // order made the page 5.7MB at 3,000 orders. Lazy, so partial reloads
            // (search, Load more) compute only what was asked for.
            'orders'            => fn () => $this->orderPage($request),
            // An order opened from a Home shortcut or a link, wherever it sits in the list.
            'focus_order'       => fn () => $request->filled('order')
                ? $this->presentOrder(CustomOrder::withTrashed()->with(['user', 'progressSnaps', 'seller:id,name'])->find((int) $request->input('order')))
                : null,
            'products'          => $products,
            'archived_products' => fn () => $request->get('tab') === 'products' ? Product::onlyTrashed()->with('category')->latest()->get() : [],
            'categories'        => Category::all(),
            'gallery'           => fn () => $request->get('tab') === 'showcase' ? GalleryImage::latest()->get() : [],
            'archived_gallery'  => fn () => $request->get('tab') === 'showcase' ? GalleryImage::onlyTrashed()->latest()->get() : [],
            'leads'             => fn () => $request->get('tab') === 'subscribers' ? Lead::latest()->get(['id', 'email', 'created_at']) : [],
            'custom_order_options' => \App\Support\CustomOrderOptions::all(),
            'payment_settings'  => PaymentSettings::forStudio(),
            'order_policy'      => \App\Support\OrderPolicy::forStudio(),
            'refunds'           => fn () => $request->get('tab') === 'refunds' ? $this->refundQueue($request) : [],
            // Built only for the Stock screen (it reconciles both models): opened directly, or reloaded by the panel.
            'stock'             => fn () => $request->user()->can('stock.manage') && $request->get('tab') === 'stock'
                ? \App\Support\StudioStock::forStudio($request->user()->can('stock.cutover')) : null,
            // Phase D2: the options, for Settings → Delivery and an order's delivery.
            'delivery_options' => $request->user()->can('money.record') || $request->user()->can('settings.policies') ? DeliveryController::forStudio() : [],
            'handover_locations' => $request->user()->can('settings.policies') ? \App\Models\Location::orderBy('sort')->get(['id', 'name', 'address', 'active', 'accepts_pickup']) : [],
            'stock_v2'          => \App\Support\StockMode::v2(),
            // Studio → Staff (owner), built only for that screen.
            'staff'             => fn () => $request->user()->can('staff.manage') && $request->get('tab') === 'staff' ? StaffController::forStudio() : null,
            'activity' => fn () => $request->user()->can('audit.review') && $request->get('tab') === 'activity' ? ActivityController::data($request) : null,
            'tills'             => fn () => $request->user()->can('till.review') && $request->get('tab') === 'tills' ? $this->tillHistory($request) : null,
            'drops'             => fn () => config('services.drops.enabled', false) && $request->get('tab') === 'drops'
                ? Drop::with(['reservations' => fn ($q) => $q->latest()])->withCount('paidReservations')->latest()->get() : [],
        ]);
    }

    public function update(Request $request, CustomOrder $order)
    {
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(array_merge(OrderStatus::PRODUCTION_STAGES, [OrderStatus::PENDING_PAYMENT, OrderStatus::PAID, OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH, OrderStatus::EXPIRED, OrderStatus::CANCELLED]))],
            'internal_notes' => 'nullable|string',
            'price_quoted' => 'nullable|numeric|min:0',
            'is_paid' => 'nullable|boolean',
            'override_reason' => 'nullable|string|max:255',
            'quote_reason' => 'nullable|string|max:255',
        ]);

        // Phase D2: a pickup is made ready and collected at the till; everything else ships.
        if ($order->fulfilment_method === 'pickup' && $validated['status'] === 'shipped') {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'This order is collected from the shop. Mark it ready for pickup instead.']);
        }
        if ($order->fulfilment_method !== 'pickup' && $validated['status'] === 'ready_for_pickup' && $order->fulfilment_status !== 'ready_for_pickup') {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'This order is delivered. It ships; it isn’t collected from the shop.']);
        }

        // A cancelled order stays at the stage it was cancelled at; only its notes can change.
        if ($order->order_state === 'cancelled') {
            if ($validated['status'] !== $order->legacyStatus() && $validated['status'] !== $order->fulfilment_status) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'This order is cancelled. Its stage stays where it was cancelled.']);
            }
            \App\Support\Audit::changes('order.updated', $order, ['internal_notes' => $validated['internal_notes'] ?? null]);
            $order->update(['internal_notes' => $validated['internal_notes'] ?? null]);

            return back()->with('success', 'Notes saved. The order stays cancelled.');
        }

        // Handing an order over checks the customer's phone and takes any balance: that is the till's job (Phase E).
        if ($validated['status'] === 'collected' && $order->fulfilment_status !== 'collected') {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Mark it collected at the till, after checking the customer’s phone.']);
        }
        if (array_key_exists('is_paid', $validated) && (bool) $validated['is_paid'] !== $order->isPaidUp()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['is_paid' => 'Payment is confirmed by the payment service and cannot be changed here.']);
        }
        $commerce = $order->isCommerce();
        $currentStatus = $order->legacyStatus();
        $newQuote = array_key_exists('price_quoted', $validated) && $validated['price_quoted'] !== null ? (int) round((float) $validated['price_quoted']) : null;
        // The quote is the work; delivery is set on its own (Phase D2) and stays out of it.
        $delivery = $order->deliveryTotal();
        $quoteChanges = $newQuote !== null && $newQuote !== $order->quoteAmount();
        // A paid bespoke quote may only come down (the excess is owed back); a checkout total never changes.
        if ($quoteChanges && ($commerce || ($order->isPaidUp() && $newQuote > $order->quoteAmount()))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['price_quoted' => 'A checkout or paid order’s total cannot be changed.']);
        }
        $quoteReason = trim((string) ($validated['quote_reason'] ?? ''));
        $paid = (int) $order->amount_paid;
        $lowersBelowPaid = $quoteChanges && $newQuote + $delivery < $paid;
        if ($lowersBelowPaid && $quoteReason === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['price_quoted' => 'KSh ' . number_format($paid) . ' is already paid, so KSh '
                . number_format($paid - $newQuote - $delivery) . ' would be owed back to the customer. Add a reason to lower the quote.']);
        }
        if ($validated['status'] !== $currentStatus && ! in_array($validated['status'], OrderStatus::PRODUCTION_STAGES, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Payment states are managed automatically. Choose a fulfilment stage.']);
        }
        if ($commerce && $validated['status'] !== $currentStatus) {
            // A part-paid shop order (a short payment, or a swap to a dearer item) never moves on, owner or not.
            if ((int) $order->amount_paid > 0 && (int) $order->balance_due > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'KSh ' . number_format((int) $order->balance_due)
                    . ' is still owed on this order. Take the balance before it leaves the studio.']);
            }
            if (! $order->isPaidUp() || ! in_array($validated['status'], OrderStatus::PRODUCTION_STAGES, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Confirm payment before updating fulfilment. Payment states are managed automatically.']);
            }
        }
        $overrideReason = trim((string) ($validated['override_reason'] ?? ''));
        unset($validated['is_paid'], $validated['override_reason'], $validated['quote_reason']);

        // N7: bespoke work starts once the deposit is paid and leaves once it is paid in full.
        if (! $commerce && $validated['status'] !== $currentStatus && ($blocked = $this->fulfilmentBlock($order, $validated['status']))) {
            if ($overrideReason === '' || ! $request->user()->can('fulfilment.override')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => $blocked]);
            }
            \App\Support\Audit::record('fulfilment.override', $order,
                ['status' => $currentStatus, 'balance_due' => max(0, (int) $order->balance_due)], ['status' => $validated['status']], $overrideReason);
        }

        \App\Support\Audit::changes('order.updated', $order, $validated);
        // What a bespoke order costs lives in its lines (append-only); price_quoted follows them.
        if ($quoteChanges) {
            $before = (int) $order->chargeable_total;
            app(OrderSettlementService::class)->setQuote($order, $newQuote,
                \App\Support\Actor::user($request->user()), $quoteReason !== '' ? $quoteReason : 'Quote set in the Studio');
            if ($lowersBelowPaid) {
                \App\Support\Audit::record('order.quote_lowered', $order, ['quote' => $before, 'paid' => $paid], ['quote' => $newQuote, 'owed_back' => $paid - $newQuote], $quoteReason);
            }
        }
        unset($validated['price_quoted']);
        $becomesReady = $validated['status'] === 'ready_for_pickup' && $order->fulfilment_status !== 'ready_for_pickup';
        $order->refresh()->update($validated + \App\Support\LegacyOrderMap::forStatus($validated['status']));

        // Phase D2: the customer learns it is ready, where, and what to bring.
        if ($becomesReady && $order->email) {
            try {
                \Illuminate\Support\Facades\Mail::to($order->email)->queue(new \App\Mail\ReadyForPickupMail($order->fresh()));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Ready-for-pickup email could not be queued.', ['order_id' => $order->id, 'exception' => get_class($e)]);
            }
        }

        return back()->with('success', $becomesReady ? 'Ready for pickup. The customer has been told.' : 'Order updated successfully.');
    }

    /**
     * Bespoke work is quoted and paid over WhatsApp, outside the M-Pesa STK
     * flow. Staff record that payment here with the amount and a reference so
     * the customer's tracking and the Studio show it as paid. Shop orders and
     * Drop reservations can only be confirmed by M-Pesa settlement.
     */
    /** Studio → Till: paginated drawers, open ones first, each with its closing report. */
    private function tillHistory(Request $request): array
    {
        $tills = app(\App\Services\TillService::class);
        $alert = \App\Support\ShopPolicy::tillVarianceAlert();

        $filters = $request->validate([
            'till_from' => ['nullable', 'date_format:Y-m-d'],
            'till_to' => ['nullable', 'date_format:Y-m-d', \Illuminate\Validation\Rule::when($request->filled('till_from'), 'after_or_equal:till_from')],
            'till_actor' => ['nullable', 'integer', 'exists:users,id'],
            'till_status' => ['nullable', 'in:open,closed'],
            'till_q' => ['nullable', 'string', 'max:80'],
            'till_page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $zone = config('app.display_timezone', 'Africa/Nairobi');
        $sessions = \App\Models\TillSession::with(['openedBy:id,name', 'closedBy:id,name', 'acknowledgedBy:id,name'])
            ->when($filters['till_from'] ?? null, fn ($q, $date) => $q->where('opened_at', '>=', Carbon::parse($date, $zone)->startOfDay()->utc()))
            ->when($filters['till_to'] ?? null, fn ($q, $date) => $q->where('opened_at', '<=', Carbon::parse($date, $zone)->endOfDay()->utc()))
            ->when($filters['till_actor'] ?? null, fn ($q, $actor) => $q->where(fn ($w) => $w->where('opened_by', $actor)->orWhere('closed_by', $actor)))
            ->when($filters['till_status'] ?? null, fn ($q, $status) => $q->where('till_sessions.status', $status))
            ->when(trim($filters['till_q'] ?? ''), fn ($q, $text) => $q->where('register', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $text) . '%'))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 ELSE 1 END")->latest('opened_at')->orderByDesc('id')
            ->paginate(30, ['*'], 'till_page');

        return [
            'filters' => $filters,
            'actors' => \App\Models\User::whereIn('id', \App\Models\TillSession::select('opened_by'))->orWhereIn('id', \App\Models\TillSession::whereNotNull('closed_by')->select('closed_by'))->orderBy('name')->get(['id', 'name']),
            'total' => $sessions->total(),
            'page' => $sessions->currentPage(),
            'last_page' => $sessions->lastPage(),
            'variance_alert' => $alert,
            'sessions' => $sessions->getCollection()
                ->map(fn ($t) => ['id' => $t->id, 'register' => $t->register, 'status' => $t->status, 'print_url' => route('studio.till.report', $t->id),
                    'opened_by' => $t->openedBy?->name, 'closed_by' => $t->closedBy?->name, 'acknowledged_by' => $t->acknowledgedBy?->name,
                    'over_alert' => $t->variance !== null && abs((int) $t->variance) > $alert, 'report' => $tills->report($t)])->values()->all(),
        ];
    }

    /** Studio order list: ?o_q= search, ?o_filter=all|custom|shop|till, ?o_archived=1, ?o_page=N. */
    private function orderPage(Request $request): array
    {
        $q = trim((string) $request->input('o_q', ''));
        $filter = $request->get('tab') === 'bespoke' ? 'bespoke' : $request->input('o_filter', 'all');

        $page = StudioWorkflow::apply(CustomOrder::query(), $request->input('o_queue'))
            ->when($request->boolean('o_archived'), fn ($query) => $query->onlyTrashed())
            ->when($filter === 'bespoke', fn ($query) => $query->ofKind('bespoke'))
            ->when($filter === 'shop', fn ($query) => $query->ofKind('shop'))
            ->when($filter === 'till', fn ($query) => $query->where('channel', 'counter'))
            // "Custom" has always meant anything that isn't a shop order (drops included).
            ->when($filter === 'custom', fn ($query) => $query->ofKind('bespoke', 'drop'))
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

        // Pay link and WhatsApp message for quoted bespoke orders (design B).
        $orders = collect($page->items())->map(fn ($order) => $this->presentOrder($order));

        return [
            'data' => $orders->all(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'archived' => $request->boolean('o_archived'),
            'queue' => in_array($request->input('o_queue'), StudioWorkflow::QUEUES, true) ? $request->input('o_queue') : 'all',
            'sort' => $request->input('o_sort') === 'oldest' ? 'oldest' : 'newest',
            'query' => $q,
            'filter' => in_array($filter, ['shop', 'custom', 'bespoke', 'till'], true) ? $filter : 'all',
        ];
    }

    /** Presentation only: transitions are still validated by update() and the settlement service. */
    private function presentOrder(?CustomOrder $order): ?CustomOrder
    {
        if (! $order) return null;
        $order->append(self::ORDER_APPENDS);
        $stage = $order->legacyStatus();
        $next = ['kind' => 'review', 'label' => 'Review order', 'stage' => null, 'blocker' => null];
        if ($order->trashed() || $order->order_state === 'cancelled' || in_array($stage, ['shipped', 'collected', OrderStatus::EXPIRED], true)) {
            $next['label'] = 'Review history';
        } elseif ((int) $order->balance_due < 0) {
            $next = array_merge($next, ['kind' => 'payment', 'label' => 'Review refund']);
        } elseif (in_array($order->resolution, ['needs_resolution', 'amount_mismatch', 'waiting_restock'], true) || in_array($stage, [OrderStatus::PAYMENT_FAILED, OrderStatus::AMOUNT_MISMATCH], true)) {
            $next = array_merge($next, ['kind' => 'payment', 'label' => 'Resolve payment']);
        } elseif ($order->isBespoke() && $order->quoteAmount() <= 0) {
            $next = array_merge($next, ['kind' => 'quote', 'label' => 'Add quote']);
        } elseif ($stage === 'ready_for_pickup') {
            $next = array_merge($next, ['kind' => 'pickup', 'label' => 'Collect at Sales Desk']);
        } else {
            $target = match ($stage) {
                'designing' => 'sewing',
                'sewing' => 'completed',
                'completed' => $order->fulfilment_method === 'pickup' ? 'ready_for_pickup' : 'shipped',
                default => $order->isBespoke() ? 'designing' : 'completed',
            };
            $blocked = $order->isBespoke() ? $this->fulfilmentBlock($order, $target)
                : (! $order->isPaidUp() ? 'Confirm payment before updating fulfilment.' : null);
            $next = ['kind' => 'stage', 'label' => ['designing' => 'Start designing', 'sewing' => 'Move to sewing', 'completed' => $order->isBespoke() ? 'Mark complete' : 'Mark packed', 'ready_for_pickup' => 'Ready for pickup', 'shipped' => 'Mark shipped'][$target], 'stage' => $target, 'blocker' => $blocked];
            if ($blocked) $next['label'] = 'Review payment before continuing';
        }
        $order->setAttribute('next_action', $next);
        return $order;
    }

    public function recordPayment(Request $request, CustomOrder $order)
    {
        // Phase D2: the total isn't known until delivery is priced, so no money is confirmed against it.
        if ($order->needsDeliveryQuote()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Price the delivery first. The total isn’t known yet.']);
        }
        if ($order->isShop() && $order->payment_method === PaymentSettings::WHATSAPP) {
            return $this->confirmWhatsAppPayment($request, $order);
        }
        if ($order->isCommerce()) {
            return back()->with('error', 'Shop orders and Drop reservations are confirmed automatically by M-Pesa.');
        }
        if ($order->isPaidUp()) {
            return back()->with('error', 'This order is already marked as paid.');
        }
        $data = $this->validatedPayment($request);
        if ($order->chargeable_total <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Add the quote before recording a payment.']);
        }

        // Booked against the balance; the quote is never overwritten (design B).
        try {
            $result = app(OrderSettlementService::class)->recordPayment($order, (int) $data['amount'], $data['method'],
                \App\Support\Actor::user($request->user()), $data['reference'], null, null, [], limitToBalance: true);
        } catch (\DomainException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => $e->getMessage()]);
        }
        if (! $result['booked']) {
            return back()->with('error', 'This payment is already recorded.');
        }

        $this->appendNote($order, sprintf('[%s] Payment recorded by %s: KSh %s, ref %s',
            now()->timezone(config('app.display_timezone'))->format('Y-m-d H:i'), $request->user()->name,
            number_format($data['amount']), $data['reference']));
        \App\Support\Audit::record('payment.recorded', $order, [], ['amount' => (int) $data['amount'], 'reference' => $data['reference']]);

        $balance = (int) $order->fresh()->balance_due;

        return back()->with('success', $balance > 0
            ? 'Payment recorded. Balance due: KSh ' . number_format($balance) . '.'
            : 'Payment recorded. The order is fully paid.');
    }

    /** Amount, method and reference for money staff record by hand; a reference is booked once (N4). */
    private function validatedPayment(Request $request): array
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', \Illuminate\Validation\Rule::in(OrderSettlementService::MANUAL_METHODS)],
            'reference' => ['required', 'string', 'max:100'],
        ], [
            'amount.integer' => 'Enter the amount in whole shillings.',
            'method.required' => 'Choose how this was paid.',
            'method.in' => 'Choose how this was paid: M-Pesa, cash or bank.',
            'reference.required' => 'Add the M-Pesa code or a short note about how this was paid.',
        ]);

        if ($existing = app(OrderSettlementService::class)->alreadyBooked($data['method'], $data['reference'], null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reference' => (\App\Models\OrderPayment::scopeFor($data['method']) === 'mpesa' ? 'This M-Pesa code' : 'This reference')
                    . " is already recorded on order {$existing->order->cipher}.",
            ]);
        }

        return $data;
    }

    private function appendNote(CustomOrder $order, string $note): void
    {
        $order->refresh();
        $order->update(['internal_notes' => trim(($order->internal_notes ? $order->internal_notes . "\n" : '') . $note)]);
    }

    /**
     * A WhatsApp shop order is paid outside M-Pesa STK (Send Money, Pochi, cash
     * on delivery…). Confirming it books the money through the same settlement
     * as an M-Pesa callback: paid_at, stock committed once, receipt email, tracking.
     */
    private function confirmWhatsAppPayment(Request $request, CustomOrder $order)
    {
        if ($order->isPaidUp()) {
            return back()->with('error', 'This order is already marked as paid.');
        }
        if ($order->isCancelled()) {
            return back()->with('error', 'This order was cancelled. Ask the customer to order again.');
        }
        $total = $order->total();
        $data = $this->validatedPayment($request);
        if ((int) $data['amount'] !== $total) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'This order’s total is KSh ' . number_format($total) . '. Confirm once the full amount has been received.',
            ]);
        }

        // Re-claim stock if the hold lapsed; money already received is recorded either way.
        $stockOk = $order->secureStockForPayment();
        $this->appendNote($order, sprintf('[%s] WhatsApp payment confirmed by %s: KSh %s, ref %s',
            now()->timezone(config('app.display_timezone'))->format('Y-m-d H:i'), $request->user()->name,
            number_format($total), $data['reference']));
        $result = app(OrderSettlementService::class)->recordPayment($order, $total, $data['method'],
            \App\Support\Actor::user($request->user()), $data['reference']);

        if ($result['stock_gone']) {
            \App\Support\Audit::record('payment.recorded', $order, [], ['amount' => $total, 'reference' => $data['reference'], 'channel' => 'whatsapp']);

            return back()->with('error', 'Payment recorded, but ' . implode(', ', $result['stock_gone'])
                . ' sold out after the hold lapsed. Nothing was oversold: choose a refund, a swap or waiting for the next batch.');
        }
        if (! $result['became_paid']) {
            return back()->with('error', 'This order is already marked as paid.');
        }
        \App\Support\Audit::record('payment.recorded', $order, [], ['amount' => $total, 'reference' => $data['reference'], 'channel' => 'whatsapp']);

        return back()->with($stockOk ? 'success' : 'error', $stockOk
            ? 'Payment confirmed. The customer’s receipt is on its way.'
            : 'Payment confirmed, but an item’s hold had lapsed and it has since sold. Check availability before packing.');
    }

    /**
     * Owner or manager: record money sent back to the customer (the Refunds
     * queue). Method, code and reason are required; a part refund leaves the
     * rest in the queue. The customer gets a refund receipt.
     */
    public function recordRefund(Request $request, CustomOrder $order)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', \Illuminate\Validation\Rule::in(OrderSettlementService::MANUAL_METHODS)],
            'reference' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'amount.integer' => 'Enter the amount in whole shillings.',
            'method.required' => 'Choose how the refund was sent.',
            'method.in' => 'Choose how the refund was sent: M-Pesa, cash or bank.',
            'reference.required' => 'Add the M-Pesa code or a short note about how it was sent.',
            'reason.required' => 'Say why this refund is being made.',
        ]);

        try {
            $refund = app(OrderSettlementService::class)->recordRefund($order, (int) $data['amount'], $data['method'],
                \App\Support\Actor::user($request->user()), $data['reference'], $data['reason']);
        } catch (\DomainException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                str_contains($e->getMessage(), 'already recorded') ? 'reference' : 'amount' => $e->getMessage(),
            ]);
        }
        \App\Support\Audit::record('refund.recorded', $order, [], ['refund_id' => $refund->id, 'amount' => $refund->amount,
            'method' => $refund->method, 'reference' => $refund->external_reference], $data['reason']);

        $still = max(0, -(int) $order->fresh()->balance_due);

        return back()->with('success', $still > 0
            ? 'Refund recorded. KSh ' . number_format($still) . ' is still owed to the customer.'
            : 'Refund recorded. Nothing more is owed to the customer.');
    }

    /**
     * The payment Attention list (Phase C): refunds owed, orders waiting for a
     * decision, amount mismatches and unexpected bespoke amounts not yet
     * confirmed by the owner. Oldest first.
     */
    private function paymentAttention(): array
    {
        $item = fn (CustomOrder $o, string $kind, string $label, string $detail, int $amount, $since) => [
            'id' => $o->id, 'reference' => $o->cipher, 'full_name' => $o->full_name, 'kind' => $kind, 'label' => $label,
            'detail' => $detail, 'amount' => $amount, 'since' => $since?->toIso8601String(),
            'age_days' => $since ? (int) $since->diffInDays(now()) : 0,
        ];

        $items = collect();
        CustomOrder::withTrashed()->where(fn ($q) => $q->where('balance_due', '<', 0)->orWhereIn('resolution', ['needs_resolution', 'amount_mismatch']))
            ->get()->each(function (CustomOrder $o) use ($items, $item) {
                if ((int) $o->balance_due < 0) {
                    $items->push($item($o, 'refund_due', 'Refund owed', $o->refund_cause ?? 'Paid more than is owed', -(int) $o->balance_due, $o->refund_due_since ?? $o->updated_at));
                }
                if ($o->resolution === 'needs_resolution') {
                    $items->push($item($o, 'needs_resolution', 'Needs a decision', $o->resolution_reason ?? 'Payment needs review', (int) $o->amount_paid, $o->resolution_since ?? $o->updated_at));
                }
                if ($o->resolution === 'amount_mismatch') {
                    $items->push($item($o, 'amount_mismatch', 'Payment amount does not match order', 'Paid KSh ' . number_format((int) $o->amount_paid) . ' of KSh ' . number_format((int) $o->chargeable_total) . ': review the payment',
                        (int) $o->amount_paid, $o->resolution_since ?? $o->updated_at));
                }
            });

        // A bespoke M-Pesa payment that differs from its prompt: recorded, but a bug or tampering signal until the owner confirms it.
        $unexpected = OrderPayment::query()
            ->join('payment_attempts', 'payment_attempts.id', '=', 'order_payments.payment_attempt_id')
            ->join('custom_orders', 'custom_orders.id', '=', 'order_payments.custom_order_id')
            ->where('order_payments.type', OrderPayment::PAYMENT)->where('custom_orders.kind', 'bespoke')
            ->whereColumn('order_payments.amount', '!=', 'payment_attempts.amount')
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('payment_reviews')->whereColumn('payment_reviews.order_payment_id', 'order_payments.id'))
            ->notReversed()
            ->get(['order_payments.custom_order_id', 'order_payments.amount', 'order_payments.created_at', 'payment_attempts.amount as prompted']);
        if ($unexpected->isNotEmpty()) {
            $orders = CustomOrder::withTrashed()->whereIn('id', $unexpected->pluck('custom_order_id'))->get()->keyBy('id');
            foreach ($unexpected as $p) {
                $items->push($item($orders[$p->custom_order_id], 'unexpected_amount', 'Unexpected amount',
                    'Unexpected amount: KSh ' . number_format($p->amount) . ' paid against a KSh ' . number_format($p->prompted) . ' prompt', (int) $p->amount, $p->created_at));
            }
        }

        return $items->sortBy([['since', 'asc'], ['id', 'asc']])->values()->all();
    }

    /** Every order that owes the customer money, oldest first (owner and manager). */
    private function refundQueue(Request $request): array
    {
        if (! $request->user()->can('refunds.mark')) {
            return [];
        }

        return CustomOrder::withTrashed()->where('balance_due', '<', 0)->orderBy('refund_due_since')->orderBy('id')->get()
            ->map(fn (CustomOrder $o) => [
                'id' => $o->id,
                'reference' => $o->cipher,
                'customer' => $o->full_name,
                'phone' => $o->whatsapp_number ?: $o->phone,
                'email' => $o->email,
                'owed' => -(int) $o->balance_due,
                'cause' => $o->refund_cause ?? 'Paid more than is owed',
                'since' => $o->refund_due_since?->toIso8601String(),
                'age_days' => $o->refund_due_since ? (int) $o->refund_due_since->diffInDays(now()) : 0,
                'whatsapp_url' => ($o->whatsapp_number ?: $o->phone)
                    ? \App\Support\WhatsApp::link($o->whatsapp_number ?: $o->phone, $this->refundMessage($o)) : null,
            ])->all();
    }

    /** The prefilled WhatsApp message telling a customer their refund is coming. */
    private function refundMessage(CustomOrder $order): string
    {
        $days = \App\Support\OrderPolicy::refundWorkingDays();
        $why = match ($order->refund_cause) {
            'Paid twice' => 'Your payment came through twice.',
            null => 'You paid more than the order total.',
            default => rtrim($order->refund_cause, '.') . '.',
        };

        return sprintf('Hi %s, this is %s about order %s. %s We will refund KSh %s to you within %d working %s.',
            strtok((string) $order->full_name, ' ') ?: 'there', \App\Support\StoreProfile::name(), $order->cipher, $why,
            number_format(-(int) $order->balance_due), $days, $days === 1 ? 'day' : 'days');
    }

    /**
     * Owner or manager: decide a late payment whose item sold out (Phase C):
     * a full refund, a swap to an item with stock, or waiting for the next
     * batch. Always with a reason.
     */
    public function resolveOrder(Request $request, CustomOrder $order)
    {
        $data = $request->validate([
            'action' => ['required', \Illuminate\Validation\Rule::in(['refund', 'swap', 'wait'])],
            'reason' => ['required', 'string', 'max:255'],
            'from_product_id' => ['required_if:action,swap', 'nullable', 'integer'],
            'to_product_id' => ['required_if:action,swap', 'nullable', 'integer'],
            'size' => ['nullable', 'string', 'max:60'],
            // Stock per size: a set is swapped to a top size and a bottom size.
            'top_size' => ['nullable', 'string', 'max:30'],
            'bottom_size' => ['nullable', 'string', 'max:30'],
        ], [
            'reason.required' => 'Say why, for the record.',
            'to_product_id.required_if' => 'Choose the item to swap to.',
        ]);

        $settlement = app(OrderSettlementService::class);
        $actor = \App\Support\Actor::user($request->user());
        $before = ['resolution' => $order->resolution, 'balance_due' => $order->balance_due];
        try {
            match ($data['action']) {
                'refund' => $order->resolution_code === 'stock_gone' && in_array($order->resolution, ['needs_resolution', 'waiting_restock'], true)
                    ? $settlement->cancelForRefund($order, $actor, $data['reason'], 'Sold out after a late payment')
                    : throw new \DomainException('action|This order has no sold-out item waiting for a decision.'),
                'wait' => $settlement->waitForRestock($order, $data['reason']),
                'swap' => $settlement->swapItem($order, (int) $data['from_product_id'], (int) $data['to_product_id'], $data['size'] ?? null, $actor, $data['reason'],
                    array_filter(['top_size' => $data['top_size'] ?? null, 'bottom_size' => $data['bottom_size'] ?? null])),
            };
        } catch (\DomainException $e) {
            [$field, $message] = str_contains($e->getMessage(), '|') ? explode('|', $e->getMessage(), 2) : ['action', $e->getMessage()];
            throw \Illuminate\Validation\ValidationException::withMessages([$field => $message]);
        }
        $fresh = $order->fresh();
        \App\Support\Audit::record('order.resolved', $order, $before,
            ['action' => $data['action'], 'resolution' => $fresh->resolution, 'balance_due' => $fresh->balance_due], $data['reason']);

        return back()->with('success', match (true) {
            $data['action'] === 'wait' => 'Noted: the order waits for the next batch.',
            $fresh->balance_due > 0 => 'Swapped. KSh ' . number_format($fresh->balance_due) . ' is due: send the customer the pay link.',
            $fresh->balance_due < 0 => 'Done. KSh ' . number_format(-$fresh->balance_due) . ' is owed back to the customer (Refunds).',
            default => 'Swapped. The order is paid and ready for fulfilment.',
        });
    }

    /** Owner: reverse a booking that should not count, with a reason (Phase C). */
    public function reversePayment(Request $request, OrderPayment $payment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Say why this booking should not count.']);
        try {
            $reversal = app(OrderSettlementService::class)->reversePayment($payment, \App\Support\Actor::user($request->user()), $data['reason']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        \App\Support\Audit::record('payment.reversed', $payment->order, ['payment_id' => $payment->id, 'amount' => $payment->amount, 'reference' => $payment->external_reference],
            ['reversal_id' => $reversal->id], $data['reason']);

        return back()->with('success', 'Booking reversed. The order’s figures are updated.');
    }

    /** Owner: confirm a flagged booking is a genuine, separate payment, with a reason. */
    public function confirmPayment(Request $request, OrderPayment $payment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        if (\Illuminate\Support\Facades\DB::table('payment_reviews')->where('order_payment_id', $payment->id)->exists() || $payment->reversal()->exists()) {
            return back()->with('error', 'This booking is already reviewed.');
        }
        app(OrderSettlementService::class)->confirmGenuine($payment, \App\Support\Actor::user($request->user()), $data['reason']);
        \App\Support\Audit::record('payment.confirmed_genuine', $payment->order, [], ['payment_id' => $payment->id, 'reference' => $payment->external_reference], $data['reason']);

        return back()->with('success', 'Booking confirmed as a genuine payment.');
    }

    /** Why a bespoke order may not move to $stage yet (N7), or null when it may. */
    private function fulfilmentBlock(CustomOrder $order, string $stage): ?string
    {
        $working = ['designing', 'sewing', 'completed'];
        // Ready for pickup is a handover, like shipping: nothing may be owed either way.
        $stage = $stage === 'ready_for_pickup' ? 'shipped' : $stage;
        if (! in_array($stage, [...$working, 'shipped'], true)) {
            return null;
        }
        if ((int) $order->chargeable_total <= 0) {
            return 'Set the quote before work starts.';
        }
        if ($stage === 'shipped' && $order->balance_due < 0) {
            return 'KSh ' . number_format(-$order->balance_due) . ' is owed back to the customer. Record the refund before it leaves the studio.';
        }
        if ($stage === 'shipped') {
            return $order->balance_due > 0
                ? 'KSh ' . number_format($order->balance_due) . ' is still owed. Take the balance before it leaves the studio.'
                : null;
        }

        return (int) $order->amount_paid < $order->depositRequired()
            ? 'Take the deposit (KSh ' . number_format($order->depositRequired()) . ') before work starts.'
            : null;
    }

    /** One bespoke order's deposit (owner or manager), with a reason. Null returns to the policy share. */
    public function setDeposit(Request $request, CustomOrder $order)
    {
        if (! $order->isBespoke()) {
            return back()->with('error', 'Only bespoke orders take a deposit. Shop and Drop orders are paid in full.');
        }
        $data = $request->validate([
            'deposit' => ['nullable', 'integer', 'min:0', 'max:' . max(0, (int) $order->chargeable_total)],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'deposit.max' => 'The deposit cannot be more than the quote (KSh ' . number_format((int) $order->chargeable_total) . ').',
            'reason.required' => 'Say why this order’s deposit differs.',
        ]);

        $before = $order->depositRequired();
        $order->update(['deposit_override' => $data['deposit']]);
        \App\Support\Audit::record('order.deposit_set', $order, ['deposit' => $before], ['deposit' => $order->fresh()->depositRequired()], $data['reason']);

        return back()->with('success', 'Deposit set to KSh ' . number_format($order->fresh()->depositRequired()) . '.');
    }

    /**
     * Cancel a bespoke order with a reason (rule N8): before designing the
     * deposit is owed back; from designing on it is kept and only what was
     * paid above it is owed back. The customer is emailed which applied.
     */
    public function cancelBespoke(Request $request, CustomOrder $order)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Say why the order is cancelled.']);
        if (! $order->isBespoke()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reason' => 'Only custom orders are cancelled here.']);
        }
        try {
            $outcome = app(OrderSettlementService::class)->cancelBespoke($order, \App\Support\Actor::user($request->user()), $data['reason']);
        } catch (\DomainException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reason' => $e->getMessage()]);
        }
        \App\Support\Audit::record('order.cancelled', $order, ['status' => $order->legacyStatus()], $outcome, $data['reason']);

        return back()->with('success', 'Order cancelled.'
            . ($outcome['retained'] > 0 ? ' Deposit of KSh ' . number_format($outcome['retained']) . ' retained.' : '')
            . ($outcome['owed_back'] > 0 ? ' KSh ' . number_format($outcome['owed_back']) . ' is owed back (Refunds).' : '')
            . ' The customer has been emailed.');
    }

    /** Cancel an unpaid WhatsApp order: stock goes back on sale. */
    public function cancelOrder(CustomOrder $order)
    {
        if ($order->isPaidUp() || $order->payment_method !== PaymentSettings::WHATSAPP || $order->legacyStatus() !== OrderStatus::PENDING_PAYMENT) {
            return back()->with('error', 'Only unpaid WhatsApp orders can be cancelled here.');
        }
        // Money received is never cancelled away: it is refunded or settled (V1 money invariant 2).
        if (! $order->isUnfunded()) {
            return back()->with('error', 'Money has been received on this order. Record the rest of the payment, or wait for it to expire into the Refunds queue.');
        }
        if (CustomOrder::whereKey($order->id)->paidUp(false)->unfunded()->update(\App\Support\LegacyOrderMap::forStatus(OrderStatus::CANCELLED)) === 0) {
            return back()->with('error', 'A payment arrived for this order just now. Refresh and check it.');
        }
        $order->releaseReservations();
        \App\Support\Audit::record('order.cancelled', $order, ['status' => $order->status], ['status' => OrderStatus::CANCELLED]);

        return back()->with('success', 'Order cancelled. Its stock is back on sale.');
    }

    public function destroy(CustomOrder $order)
    {
        if ($order->paymentAttempts()->where('status', PaymentAttempt::PENDING)->exists()) {
            return back()->with('error', 'This order has an M-Pesa payment still in progress. Archive it after the payment succeeds, fails or expires.');
        }
        if ($order->isPaidUp() && ! in_array($order->legacyStatus(), OrderStatus::PRODUCTION_TERMINAL, true)) {
            return back()->with('error', 'Paid orders can be archived once they are completed or dispatched.');
        }
        // Part-paid, or a payment awaiting a decision: archiving would take it out of the
        // jobs and lists that settle or refund it (V1 money invariant 2).
        if (! $order->isPaidUp() && ! $order->isUnfunded()) {
            return back()->with('error', 'Money has been received on this order and it is not settled. Resolve the payment or refund before archiving.');
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
        // Money on the books (or any STK attempt) is evidence: never purged.
        if ($order->isPaidUp() || $order->payments()->exists() || $order->paymentAttempts()->exists()) {
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
