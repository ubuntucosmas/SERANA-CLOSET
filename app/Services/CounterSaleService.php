<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\TillSession;
use App\Models\User;
use App\Support\Actor;
use App\Support\Audit;
use App\Support\LegacyOrderMap;
use App\Support\OrderStatus;
use App\Support\Phone;
use App\Support\ShopPolicy;
use App\Support\StockMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A sale at the counter (design §15.3). It is an ordinary shop order on the
 * 'counter' channel, so the ledger, receipts, refunds and the Phase C rules
 * all apply unchanged. Stock is held at the till's shop; money goes through
 * OrderSettlementService and cash through TillService.
 */
class CounterSaleService
{
    public function __construct(private StockService $stock, private OrderSettlementService $settlement, private TillService $tills)
    {
    }

    /**
     * Ring up a sale: hold its stock at this shop for pos_hold_minutes, then
     * any discount (over the cashier's limit only with an approver).
     *
     * @param  array<int, array{id: int, quantity: int, options?: ?array, variant_ids?: ?array}>  $items
     */
    public function start(TillSession $till, User $seller, array $items, array $customer = [], int $discount = 0, ?string $discountReason = null, ?User $approver = null): CustomOrder
    {
        $products = Product::whereIn('id', collect($items)->pluck('id'))->get()->keyBy('id');
        foreach ($items as $item) {
            $product = $products->get($item['id']);
            if (! $product || ! $product->in_stock) {
                throw ValidationException::withMessages(['items' => ($product?->name ?? 'An item') . ' is not for sale.']);
            }
        }
        $snapshot = [];
        $subtotal = 0;
        foreach (array_values($items) as $item) {
            $product = $products[$item['id']];
            $subtotal += (int) round((float) $product->price) * (int) $item['quantity'];
            $snapshot[] = ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'price' => (float) $product->price,
                'quantity' => (int) $item['quantity'], 'image_path' => $product->image_path, 'options' => $item['options'] ?? null];
        }
        if ($discount < 0 || $discount >= $subtotal && $subtotal > 0) {
            throw ValidationException::withMessages(['discount' => 'A discount must be less than the sale.']);
        }

        $location = $till->location;
        $v2 = StockMode::v2();
        $demands = [];
        if ($v2) {
            $plan = $this->stock->demandsFor($items, $products);
            if ($plan['errors']) {
                throw ValidationException::withMessages(['items' => $plan['errors'][0]['message']]);
            }
            foreach ($plan['variant_ids'] as $index => $ids) {
                $snapshot[$index]['variant_ids'] = $ids;
            }
            $demands = $plan['demands'];
        }

        try {
            $order = DB::transaction(function () use ($till, $seller, $customer, $snapshot, $subtotal, $products, $items, $v2, $demands, $location) {
                $until = now()->addMinutes(ShopPolicy::posHoldMinutes());
                // Lock order step 1: the sizes at this shop (v2), or the products (the old model).
                $allocations = $v2 ? $this->stock->allocate($demands, $location) : [];
                $productHolds = $v2 ? [] : $this->stock->lockProductsForHold(collect($items)->groupBy('id')->map(fn ($lines) => (int) $lines->sum('quantity'))->all());

                $phone = ! empty($customer['phone']) ? Phone::normalize($customer['phone']) : null;
                $order = CustomOrder::create([
                    'full_name' => trim((string) ($customer['name'] ?? '')) ?: 'Walk-in customer',
                    // A walk-in leaves no number (the column is required, so it is empty).
                    'email' => $customer['email'] ?? null, 'phone' => $phone, 'whatsapp_number' => $phone ?? '',
                    'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'payment_method' => 'counter',
                    'price_quoted' => $subtotal, 'items_json' => $snapshot, 'is_paid' => false,
                    'till_session_id' => $till->id, 'sold_by' => $seller->id,
                ]);
                $this->stock->hold($order, $allocations, $until);
                foreach ($productHolds as $productId => $quantity) {
                    $order->stockReservations()->create(['product_id' => $productId, 'quantity' => $quantity, 'status' => 'held', 'expires_at' => $until]);
                }

                return $order;
            }, 3);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        if ($discount > 0) {
            $this->settlement->addDiscount($order, $discount, Actor::user($seller), (string) $discountReason, $approver?->id);
        }
        Audit::record('pos.sale_started', $order, [], ['till' => $till->register, 'total' => (int) $order->fresh()->chargeable_total, 'approved_by' => $approver?->id], null, Actor::user($seller));

        return $order->fresh();
    }

    /**
     * Take money at the till. Cash books at most what is owed: the change is
     * handed back, never booked, and only what is booked lands in the drawer.
     *
     * @return array{booked: int, change: int}
     */
    public function tenderCash(CustomOrder $order, TillSession $till, User $by, int $amount, int $tendered, ?\DateTimeInterface $at = null): array
    {
        $balance = max(0, (int) $order->fresh()->balance_due);
        if ($balance <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nothing more is owed on this sale.']);
        }
        if ($tendered < $amount) {
            throw ValidationException::withMessages(['tendered' => 'The cash handed over is less than the amount to pay.']);
        }
        $booked = min($amount, $balance);
        $this->secure($order);

        DB::transaction(function () use ($order, $till, $by, $booked, $tendered, $at) {
            $result = $this->settlement->recordPayment($order, $booked, 'cash', Actor::user($by), null, null, 'Cash at ' . $till->register, [], false, $till->id, $at);
            $session = $this->tills->lock($till); // the last lock
            $this->tills->cash($session, CashMovement::SALE, $booked, Actor::user($by), null,
                ['order_payment_id' => $result['payment']?->id, 'custom_order_id' => $order->id, 'tendered' => $tendered] + ($at ? ['occurred_at' => $at] : []));
        }, 3);

        return ['booked' => $booked, 'change' => $tendered - $booked];
    }

    /** An M-Pesa code the customer shows (Send Money / till number); unique like every code. */
    public function tenderMpesaCode(CustomOrder $order, TillSession $till, User $by, int $amount, string $code, ?\DateTimeInterface $at = null): void
    {
        if ($holder = $this->settlement->referenceHolder('mpesa_manual', $code)) {
            throw ValidationException::withMessages(['reference' => "This M-Pesa code is already recorded on order {$holder->order->cipher}."]);
        }
        $this->secure($order);
        try {
            $this->settlement->recordPayment($order, $amount, 'mpesa_manual', Actor::user($by), $code, null, 'M-Pesa at ' . $till->register, [], true, $till->id, $at);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }
    }

    /**
     * An exchange (design §15.4): what an earlier sale owes back pays for
     * this one. A 'credit' refund on the old sale and a 'credit' payment on
     * this one, in one transaction; no cash moves. At most what is owed back
     * and what this sale still owes.
     */
    public function tenderCredit(CustomOrder $order, TillSession $till, User $by, int $amount, CustomOrder $from): int
    {
        $owedBack = max(0, -(int) $from->fresh()->balance_due);
        if ($owedBack <= 0) {
            throw ValidationException::withMessages(['credit_order_id' => "Nothing is owed back on {$from->cipher} to use as credit."]);
        }
        $balance = max(0, (int) $order->fresh()->balance_due);
        if ($balance <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nothing more is owed on this sale.']);
        }
        $credit = min($amount, $owedBack, $balance);
        $this->secure($order);
        DB::transaction(function () use ($order, $till, $by, $credit, $from) {
            $n = $from->payments()->where('type', \App\Models\OrderPayment::REFUND)->where('method', 'credit')->count() + 1;
            $this->settlement->recordRefund($from, $credit, 'credit', Actor::user($by), "Credit to {$order->cipher} #{$n}", "Exchanged for {$order->cipher}", $till->id);
            $this->settlement->recordPayment($order, $credit, 'credit', Actor::user($by), "Credit from {$from->cipher} #{$n}", null, "Exchange from {$from->cipher}", [], true, $till->id);
        }, 3);

        return $credit;
    }

    /** A prompt to the customer's phone; the callback books it to this till like any online payment. */
    public function tenderMpesaPrompt(CustomOrder $order, User $by, int $amount, string $phone): PaymentAttempt
    {
        $phone = Phone::normalize($phone);
        if (! Phone::isValidKenyanMobile($phone)) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Kenyan mobile number, e.g. 07XX XXX XXX.']);
        }
        $balance = max(0, (int) $order->fresh()->balance_due);
        if ($amount < 1 || $amount > $balance) {
            throw ValidationException::withMessages(['amount' => $balance > 0 ? 'Up to KSh ' . number_format($balance) . ' is owed.' : 'Nothing more is owed on this sale.']);
        }
        $this->secure($order);
        $result = app(MpesaService::class)->initiateStkPush($phone, $amount, "ORDER-{$order->id}", "Serana sale {$order->cipher}");
        if (! ($result['success'] ?? false)) {
            throw ValidationException::withMessages(['phone' => 'M-Pesa couldn’t send the prompt. Check the number, or take another tender.']);
        }
        $attempt = $order->paymentAttempts()->create(['checkout_request_id' => $result['data']['CheckoutRequestID'], 'phone' => $phone, 'amount' => $amount, 'status' => PaymentAttempt::PENDING]);
        $order->update(['mpesa_checkout_id' => $attempt->checkout_request_id]);
        Audit::record('pos.mpesa_prompt', $order, [], ['amount' => $amount, 'attempt' => $attempt->id], null, Actor::user($by));

        return $attempt;
    }

    /** Cancel an unpaid sale: its stock goes back on sale. Money taken means a refund, not a void. */
    public function void(CustomOrder $order, User $by, string $reason, ?User $approver): void
    {
        DB::transaction(function () use ($order, $by, $reason, $approver) {
            $locked = $this->settlement->lockForMoney($order);
            if ((int) $locked->amount_paid > 0) {
                throw ValidationException::withMessages(['reason' => 'Money was taken on this sale: refund it instead of voiding.']);
            }
            if ($locked->order_state !== 'open') {
                throw ValidationException::withMessages(['reason' => 'This sale is already closed.']);
            }
            $locked->releaseReservations();
            CustomOrder::whereKey($locked->id)->update(LegacyOrderMap::forStatus(OrderStatus::CANCELLED));
        }, 3);
        Audit::record('pos.sale_voided', $order, [], ['approved_by' => $approver?->id], $reason, Actor::user($by));
    }

    /** Re-take the sale's holds before money is taken (a slow customer may outlast them). */
    private function secure(CustomOrder $order): void
    {
        if (! $order->secureStockForPayment(now()->addMinutes(ShopPolicy::posHoldMinutes()))) {
            throw ValidationException::withMessages(['items' => 'An item on this sale sold out while it waited. Void the sale and start again.']);
        }
    }

    /** What the till screen shows for a sale. */
    public function present(CustomOrder $order): array
    {
        $order->refresh();

        return [
            'id' => $order->id, 'reference' => $order->cipher, 'customer' => $order->full_name,
            'lines' => $order->lines()->orderBy('id')->get()->map(fn ($l) => ['description' => $l->description, 'quantity' => $l->quantity, 'amount' => $l->amount, 'type' => $l->line_type])->all(),
            'total' => (int) $order->chargeable_total, 'paid_so_far' => (int) $order->amount_paid, 'balance_due' => max(0, (int) $order->balance_due),
            'paid' => (int) $order->chargeable_total > 0 && (int) $order->balance_due <= 0 && $order->resolution === 'none',
            'payments' => $order->payments()->orderBy('id')->get()->map(fn ($p) => ['type' => $p->type, 'method' => $p->method, 'amount' => $p->amount, 'reference' => $p->external_reference])->all(),
            'state' => $order->order_state, 'fulfilment' => $order->fulfilment_status, 'resolution' => $order->resolution,
            'prompt' => ($attempt = $order->paymentAttempts()->latest('id')->first()) ? ['status' => $attempt->status, 'amount' => $attempt->amount] : null,
        ];
    }
}
