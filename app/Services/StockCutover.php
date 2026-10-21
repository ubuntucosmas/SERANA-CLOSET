<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\ThemeSetting;
use App\Support\Actor;
use App\Support\Audit;
use App\Support\StockMode;
use Illuminate\Support\Facades\DB;

/**
 * The switch to stock per size (design §13.5.4–6), and back. Confirm is
 * refused until it is safe: every tracked size counted, every live hold
 * readable as a size, every old sale after a count readable. Then, in one
 * transaction: the old model's sales after each count become sale
 * movements, the batch numbers are re-baselined to the counts, and v2 is
 * switched on. Rollback switches it off; the dual write keeps the old
 * model's totals right meanwhile.
 */
class StockCutover
{
    public function __construct(private StockService $stock, private StockReconciler $reconciler, private StockBackfill $backfill)
    {
    }

    public function preview(): array
    {
        $report = $this->reconciler->report();
        // Only live holds block the switch: a shopper is paying for them right now.
        $report['unmapped_holds'] = StockReservation::where('status', 'held')->whereNull('product_variant_id')
            ->with('order')->get()->map(fn ($h) => $h->order?->cipher)->filter()->unique()->values()->all();
        // Old released holds whose size was never recorded: harmless, but a late payment on one waits for the studio.
        $report['unmapped_released'] = StockReservation::where('status', 'released')->whereNull('product_variant_id')
            ->with('order')->get()->map(fn ($h) => "Order {$h->order?->cipher}: a released hold with no size (a late payment would wait for the studio)")
            ->unique()->values()->all();
        $report['ready'] = ! $report['v2'] && $report['uncounted'] === [] && $report['unmapped_holds'] === []
            && $report['unreadable_sales'] === [] && $report['integrity'] === [];

        return $report;
    }

    /** @return array{replayed: int, rebaselined: int} */
    public function confirm(Actor $actor): array
    {
        if (StockMode::v2()) {
            throw new \DomainException('Stock per size is already on.');
        }
        $this->backfill->run(); // map any hold placed since the deploy
        $preview = $this->preview();
        if ($preview['uncounted']) {
            throw new \DomainException('Count every size first: ' . implode(', ', $preview['uncounted']) . '.');
        }
        if ($preview['unmapped_holds']) {
            throw new \DomainException(count($preview['unmapped_holds']) . ' hold(s) on order ' . implode(', ', $preview['unmapped_holds'])
                . " can't be read as a size. Let them expire, or cancel those orders, then confirm again.");
        }
        if ($preview['unreadable_sales'] || $preview['integrity']) {
            throw new \DomainException('Fix these first: ' . implode('; ', [...$preview['unreadable_sales'], ...$preview['integrity']]) . '.');
        }

        $result = DB::transaction(function () use ($actor) {
            $replayed = 0;
            foreach ($this->reconciler->replays() as $r) {
                try {
                    $this->stock->move($r['variant'], \App\Models\Location::findOrFail($r['location_id']), StockMovement::SALE, -$r['quantity'], Actor::system(),
                        'Sold before the switch', ['custom_order_id' => $r['order']->id, 'stock_reservation_id' => $r['reservation']->id]);
                } catch (\DomainException $e) {
                    throw new \DomainException("Recount {$this->stock->describe($r['variant'])}: order {$r['order']->cipher} sold one after the count, which would leave less than zero. ({$e->getMessage()})");
                }
                $replayed++;
            }
            $rebaselined = 0;
            Product::where('track_stock', true)->each(function (Product $p) use (&$rebaselined) {
                $this->stock->rebaseline($p);
                $rebaselined++;
            });
            Audit::record('stock.cutover', null, ['stock_v2' => false], ['stock_v2' => true, 'replayed' => $replayed, 'rebaselined' => $rebaselined],
                'Stock take confirmed', $actor);

            return ['replayed' => $replayed, 'rebaselined' => $rebaselined];
        }, 3);
        ThemeSetting::set(StockMode::SETTING, '1');

        return $result;
    }

    public function rollback(Actor $actor, string $reason): void
    {
        if (trim($reason) === '') {
            throw new \DomainException('Say why stock per size is being switched off.');
        }
        ThemeSetting::set(StockMode::SETTING, '0');
        Audit::record('stock.rollback', null, ['stock_v2' => true], ['stock_v2' => false], $reason, $actor);
    }
}
