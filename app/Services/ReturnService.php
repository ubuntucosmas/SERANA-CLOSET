<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CustomOrder;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TillSession;
use App\Models\User;
use App\Support\Actor;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Returns and exchanges at the till (design §15.4). Stock through
 * StockService, money through OrderSettlementService, cash through
 * TillService; one transaction in the global lock order (stock levels, the
 * order row, ledger rows, then the till).
 */
class ReturnService
{
    public const METHODS = ['cash', 'mpesa_manual', 'later', 'credit'];

    public function __construct(private StockService $stock, private OrderSettlementService $settlement, private TillService $tills)
    {
    }

    /**
     * @param  list<array{order_line_id: int, quantity: int, disposition: string}>  $lines
     * @return array{returned: int, refunded: int}
     */
    public function process(CustomOrder $order, TillSession $till, User $by, array $lines, string $reason, string $method, ?string $reference, ?User $approver): array
    {
        $returns = [];
        foreach ($lines as $l) {
            $line = OrderLine::where('custom_order_id', $order->id)->where('line_type', OrderLine::ITEM)->find($l['order_line_id'])
                ?? throw ValidationException::withMessages(['lines' => 'That line isn’t on this sale.']);
            $returns[] = ['line' => $line, 'quantity' => (int) $l['quantity'], 'disposition' => $l['disposition']];
        }
        $value = (int) collect($returns)->sum(fn ($r) => (int) $r['line']->unit_amount * $r['quantity']);
        if ($method === 'cash' && $value > ($inDrawer = $this->tills->expectedCash($till))) {
            throw ValidationException::withMessages(['refund_method' => 'Only KSh ' . number_format($inDrawer) . ' is in the drawer. Refund by M-Pesa, or later from the Studio.']);
        }
        $actor = Actor::user($by);

        $refunded = DB::transaction(function () use ($order, $till, $returns, $reason, $method, $reference, $approver, $actor) {
            // 1. Stock (level locks), 2. the lines (order lock), 3. the refund (ledger), 4. cash (till lock).
            foreach ($returns as $r) {
                if ($r['line']->product_id && ($product = Product::withTrashed()->find($r['line']->product_id))) {
                    $variants = ProductVariant::resolve($product, $r['line']->meta ?? null, activeOnly: false) ?? [];
                    $this->stock->takeBack($product, $variants, $till->location, $r['quantity'], $r['disposition'], $actor, $reason, $order->id);
                }
            }
            $this->settlement->returnItems($order, $returns, $actor, $reason, $approver?->id);

            if (! in_array($method, ['cash', 'mpesa_manual'], true)) {
                return 0; // later: the Studio's Refunds queue; credit: spent on the exchange sale
            }
            $owed = max(0, -(int) $order->fresh()->balance_due);
            if ($owed === 0) {
                return 0;
            }
            $ref = $method === 'cash'
                ? "Cash refund {$till->register} {$order->cipher}-" . ($order->payments()->where('type', OrderPayment::REFUND)->count() + 1)
                : (string) $reference;
            if ($method === 'mpesa_manual' && ($holder = $this->settlement->referenceHolder('mpesa_manual', $ref))) {
                throw ValidationException::withMessages(['refund_reference' => "This M-Pesa code is already recorded on order {$holder->order->cipher}."]);
            }
            $refund = $this->settlement->recordRefund($order, $owed, $method, $actor, $ref, $reason, $till->id);
            if ($method === 'cash') {
                $session = $this->tills->lock($till);
                $this->tills->cash($session, CashMovement::REFUND, -$owed, $actor, $reason,
                    ['order_payment_id' => $refund->id, 'custom_order_id' => $order->id, 'approved_by' => $approver?->id]);
            }

            return $owed;
        }, 3);

        Audit::record('pos.return', $order, [], ['lines' => collect($returns)->map(fn ($r) => ['line' => $r['line']->id, 'quantity' => $r['quantity'], 'disposition' => $r['disposition']])->all(),
            'value' => (int) collect($returns)->sum(fn ($r) => (int) $r['line']->unit_amount * $r['quantity']), 'refund' => $method, 'refunded' => $refunded, 'approved_by' => $approver?->id],
            $reason, $actor);

        return ['returned' => $value, 'refunded' => $refunded];
    }

    /** What the till shows when it finds a sale: each line and how much of it can still come back. */
    public function present(CustomOrder $order): array
    {
        return [
            'id' => $order->id, 'reference' => $order->cipher, 'customer' => $order->full_name, 'channel' => $order->channel,
            'total' => (int) $order->chargeable_total, 'paid' => (int) $order->amount_paid, 'owed_back' => max(0, -(int) $order->balance_due),
            'lines' => $order->lines()->where('line_type', OrderLine::ITEM)->orderBy('id')->get()->map(fn (OrderLine $l) => [
                'id' => $l->id, 'description' => $l->description, 'quantity' => (int) $l->quantity, 'unit' => (int) $l->unit_amount,
                'returnable' => $this->settlement->returnable($l),
            ])->all(),
        ];
    }
}
