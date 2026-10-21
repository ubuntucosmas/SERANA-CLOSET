<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\TillSession;
use App\Models\User;
use App\Support\Actor;
use App\Support\Audit;
use App\Support\LegacyOrderMap;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The orders desk at the till (design §15.5). Click & collect hands over an
 * order only to the phone on it and only when nothing is owed; bespoke
 * intake makes the same order the website does; a late entry books a paper
 * slip from an outage once, at its original time. Money still goes through
 * CounterSaleService (and so OrderSettlementService and TillService).
 */
class ShopDeskService
{
    public function __construct(private OrderSettlementService $settlement, private CounterSaleService $sales, private StockService $stock)
    {
    }

    /** The order with this reference whose phone matches; null for either kind of miss (nothing leaks). */
    public function findForCollection(string $reference, string $phone): ?CustomOrder
    {
        $order = CustomOrder::where('cipher', strtoupper(trim($reference)))->first();

        return $order && $this->phoneMatches($order, $phone) ? $order : null;
    }

    public function phoneMatches(CustomOrder $order, string $phone): bool
    {
        $typed = Phone::normalize($phone);

        return $typed !== '' && collect([$order->phone, $order->whatsapp_number])
            ->filter()->contains(fn ($p) => Phone::normalize($p) === $typed);
    }

    /**
     * Why this order can't be handed over yet, as [field, message], or null.
     * A bespoke piece must be finished; nothing may be owed or in dispute.
     */
    public function collectBlocker(CustomOrder $order): ?array
    {
        $balance = (int) $order->balance_due;

        return match (true) {
            $order->fulfilment_status === 'collected' => ['order', 'This order was already collected.'],
            $order->fulfilment_status === 'shipped' => ['order', 'This order was sent by delivery.'],
            $order->order_state !== 'open' => ['order', 'This order is ' . $order->order_state . '.'],
            ($order->resolution ?? 'none') !== 'none' => ['order', 'This order has a payment problem. Resolve it in the Studio first.'],
            (int) $order->chargeable_total <= 0 => ['order', 'Nothing is charged on this order yet.'],
            $balance > 0 => ['balance', 'KSh ' . number_format($balance) . ' is still owed. Take it first.'],
            $order->kind === 'bespoke' && $order->fulfilment_status !== 'completed' => ['order', 'This piece isn’t finished yet.'],
            default => null,
        };
    }

    /** Hand the order over: the phone checked, nothing owed. Under the order lock, so a refund can't slip in between. */
    public function collect(CustomOrder $order, User $by, string $phone, ?string $note, TillSession $till): void
    {
        if (! $this->phoneMatches($order, $phone)) {
            throw ValidationException::withMessages(['phone' => 'That phone number isn’t the one on this order.']);
        }
        DB::transaction(function () use ($order) {
            $locked = $this->settlement->lockForMoney($order);
            if ($blocked = $this->collectBlocker($locked)) {
                throw ValidationException::withMessages([$blocked[0] => $blocked[1]]);
            }
            CustomOrder::whereKey($locked->id)->update(LegacyOrderMap::forStatus('collected'));
        }, 3);
        Audit::record('pos.collected', $order, [], ['till' => $till->register], $note !== null && trim($note) !== '' ? trim($note) : null, Actor::user($by));
    }

    /**
     * A bespoke order taken at the counter: the website's fields, reference
     * photos already stored, and an optional quote (the deposit is then a
     * tender like any other).
     *
     * @param  array<int, string>  $imagePaths
     */
    public function intake(User $by, array $data, array $imagePaths): CustomOrder
    {
        $phone = Phone::normalize($data['phone']);
        $order = CustomOrder::create([
            'full_name' => $data['full_name'], 'email' => $data['email'] ?? null, 'phone' => $phone, 'whatsapp_number' => $phone,
            'fabric_preference' => $data['fabric_preference'] ?? null,
            'bust_cm' => $data['bust_cm'] ?? null, 'waist_cm' => $data['waist_cm'] ?? null,
            'hips_cm' => $data['hips_cm'] ?? null, 'length_cm' => $data['length_cm'] ?? null,
            'inspiration_image_paths' => $imagePaths,
            'type' => $data['garment'] ?? 'custom',
            'sold_by' => $by->id,
            'items_json' => [
                'location' => $data['location'] ?? null, 'selected_color' => $data['selected_color'] ?? null,
                'notes' => $data['notes'] ?? '', 'deadline' => $data['deadline'] ?? null, 'occasion' => $data['occasion'] ?? null,
                'selected_fit' => $data['selected_fit'] ?? null, 'height_cm' => $data['height_cm'] ?? null, 'source' => 'till',
            ],
        ]);
        if (! empty($data['quote'])) {
            $this->settlement->setQuote($order, (int) $data['quote'], Actor::user($by), 'Quoted at the till');
        }
        Audit::record('pos.intake', $order, [], ['quote' => (int) ($data['quote'] ?? 0), 'images' => count($imagePaths)], null, Actor::user($by));

        return $order->fresh();
    }

    /**
     * A sale from a paper slip (an outage): the stock moves now, the money
     * goes into the open till, and both carry the slip's time. The payments
     * must cover the sale exactly; a slip is entered once.
     *
     * @param  array<int, array{method: string, amount: int, reference?: ?string}>  $payments
     */
    public function lateEntry(TillSession $till, User $by, User $approver, string $slip, \DateTimeInterface $at, string $reason, array $items, array $payments, array $customer = []): CustomOrder
    {
        $slip = trim($slip);
        if (CustomOrder::withTrashed()->where('slip_number', $slip)->exists()) {
            throw ValidationException::withMessages(['slip_number' => "Slip {$slip} was already entered."]);
        }
        $prices = Product::whereIn('id', collect($items)->pluck('id'))->pluck('price', 'id');
        $total = (int) collect($items)->sum(fn ($i) => (int) round((float) ($prices[$i['id']] ?? 0)) * (int) $i['quantity']);
        $paid = (int) collect($payments)->sum('amount');
        if ($paid !== $total) {
            throw ValidationException::withMessages(['payments' => 'The payments (KSh ' . number_format($paid) . ') must add up to the sale (KSh ' . number_format($total) . ').']);
        }
        $codes = collect($payments)->where('method', 'mpesa_manual')->pluck('reference')->map(fn ($c) => strtoupper(trim((string) $c)));
        if ($codes->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['payments' => 'The same M-Pesa code is on the slip twice.']);
        }
        foreach ($codes as $code) {
            if ($holder = $this->settlement->referenceHolder('mpesa_manual', $code)) {
                throw ValidationException::withMessages(['payments' => "M-Pesa code {$code} is already recorded on order {$holder->order->cipher}."]);
            }
        }

        // The pieces physically left the shop: the slip is always recorded. Where the books gave them
        // to someone else since, the slip takes them back first, in the same transaction as its sale.
        $products = Product::whereIn('id', collect($items)->pluck('id'))->get()->keyBy('id');
        try {
            $order = DB::transaction(function () use ($till, $by, $items, $customer, $products, $slip, $at) {
                $taken = $this->stock->makeRoomForSlip($items, $products, $till->location, $slip, Actor::user($by));
                foreach ($taken as $orderId => $pieces) {
                    $this->settlement->stockTakenForSlip(CustomOrder::withTrashed()->findOrFail($orderId), $pieces, $slip, Actor::user($by));
                }
                $order = $this->sales->start($till, $by, $items, $customer);
                // The unique slip number is the backstop: a second entry of the same slip undoes all of this.
                CustomOrder::whereKey($order->id)->update(['slip_number' => $slip, 'created_at' => $at]);

                return $order;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slip_number' => "Slip {$slip} was already entered."]);
        }
        try {
            foreach ($payments as $p) {
                $p['method'] === 'cash'
                    ? $this->sales->tenderCash($order, $till, $by, (int) $p['amount'], (int) $p['amount'], $at)
                    : $this->sales->tenderMpesaCode($order, $till, $by, (int) $p['amount'], strtoupper(trim((string) $p['reference'])), $at);
            }
        } catch (ValidationException $e) {
            // Nothing half-entered: an untouched sale is voided, and its stock goes back.
            if ((int) $order->fresh()->amount_paid === 0) {
                $this->sales->void($order, $by, "Late entry of slip {$slip} failed", $approver);
                CustomOrder::whereKey($order->id)->update(['slip_number' => null]);
            }
            throw $e;
        }
        Audit::record('pos.late_entry', $order, [], ['slip' => $slip, 'occurred_at' => $at->format(DATE_ATOM), 'approved_by' => $approver->id, 'total' => $total], $reason, Actor::user($by));

        return $order->fresh();
    }

    /** What the desk shows for an order. */
    public function present(CustomOrder $order): array
    {
        $order->refresh();
        $blocked = $this->collectBlocker($order);

        return [
            'id' => $order->id, 'reference' => $order->cipher, 'customer' => $order->full_name, 'kind' => $order->kind, 'garment' => $order->garment,
            'lines' => $order->lines()->orderBy('id')->get()->map(fn ($l) => ['description' => $l->description, 'quantity' => $l->quantity, 'amount' => $l->amount])->all(),
            'total' => (int) $order->chargeable_total, 'paid' => (int) $order->amount_paid, 'balance_due' => max(0, (int) $order->balance_due),
            'deposit_required' => $order->depositRequired(), 'payment_status' => $order->payment_status,
            'fulfilment' => $order->fulfilment_status, 'state' => $order->order_state,
            'can_collect' => $blocked === null, 'collect_blocker' => $blocked[1] ?? null,
        ];
    }
}
