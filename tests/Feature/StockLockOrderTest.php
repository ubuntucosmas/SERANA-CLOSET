<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Phase D1 (design §13.3): the global lock order is stock levels by id, then
 * stock holds by id, then the order row, then ledger rows. Only StockService
 * takes stock locks under stock per size, so the order is decided in one
 * place. The old model's product locks are allowed where they already were,
 * until stock per size is the only model; nothing new may add one.
 */
class StockLockOrderTest extends TestCase
{
    /** Where each kind of stock lock may be taken (path => allowed). */
    private const ALLOWED = [
        'stock_levels' => ['Services/StockService.php'],
        'stock_reservations' => ['Services/StockService.php', 'Models/CustomOrder.php'],
        // The old product-level model (retired with batch_limit, design §13.2).
        'products' => ['Services/StockService.php', 'Http/Controllers/CheckoutController.php', 'Models/CustomOrder.php', 'Services/OrderSettlementService.php'],
    ];

    public function test_stock_is_locked_only_where_the_lock_order_is_kept(): void
    {
        $patterns = [
            'stock_levels' => '/StockLevel::|stock_levels/',
            'stock_reservations' => '/StockReservation::|stockReservations\(\)|stock_reservations/',
            'products' => '/Product::/',
        ];
        $violations = [];
        foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            foreach (preg_split('/\R/', $file->getContents()) as $n => $line) {
                if (! preg_match('/lockForUpdate\(|sharedLock\(/', $line) || preg_match('/^\s*(\*|\/\/)/', $line)) {
                    continue;
                }
                foreach ($patterns as $kind => $pattern) {
                    if (preg_match($pattern, $line) && ! in_array($path, self::ALLOWED[$kind], true)) {
                        $violations[] = "{$path}:" . ($n + 1) . " locks {$kind}: " . trim($line);
                    }
                }
            }
        }

        $this->assertSame([], $violations, 'Take stock locks through StockService (lockLevels / lockForOrder), in the global order.');
    }
}
