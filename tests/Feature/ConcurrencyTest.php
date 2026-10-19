<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\StockReservation;
use App\Support\OrderStatus;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Real races on a real database. Each actor is a separate PHP process with its
 * own MariaDB connection, running the app's own code (tests/Concurrency/worker.php).
 *
 * To make the interleaving deterministic, the test first takes the product's
 * row lock itself, starts the workers one at a time and waits until each is
 * queued on a lock, then lets go. SQLite has no row locks, so this skips there.
 *
 * Run: DB_CONNECTION=mariadb DB_DATABASE=testing ... php artisan test
 */
class ConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mariadb') {
            $this->markTestSkipped('Concurrency tests need MariaDB row locks; SQLite has none. Set DB_CONNECTION=mariadb and DB_DATABASE to a test database (e.g. testing) to run them.');
        }
    }

    protected function tearDown(): void
    {
        // Leave committed rows behind for no one: later tests use RefreshDatabase.
        if (DB::connection()->getDriverName() === 'mariadb') {
            $this->truncateDatabaseTables();
        }

        parent::tearDown();
    }

    public function test_two_checkouts_racing_for_the_last_unit_sell_it_once(): void
    {
        $product = $this->product(batchLimit: 1);

        $results = $this->whileHoldingProduct($product, [
            ['checkout', $this->checkoutPayload($product, 'Buyer One')],
            ['checkout', $this->checkoutPayload($product, 'Buyer Two')],
        ]);

        $statuses = collect($results)->pluck('status')->sort()->values()->all();
        $this->assertSame([200, 422], $statuses, json_encode($results));
        $this->assertStringContainsString('sold out', collect($results)->firstWhere('status', 422)['body']['message']);

        $this->assertSame(1, (int) StockReservation::where('product_id', $product->id)->where('status', 'held')->sum('quantity'));
        $this->assertSame(1, CustomOrder::count());
        $this->assertSame(0, $product->fresh()->availableStock());
    }

    public function test_a_checkout_and_a_payment_retry_on_the_same_product_do_not_deadlock(): void
    {
        $product = $this->product(batchLimit: 2);
        // An earlier order whose hold lapsed: the STK retry must re-acquire it.
        $retrying = $this->unpaidOrderHolding($product, 'released');

        // Checkout queues on the product first, then the retry; nothing may fail.
        $results = $this->whileHoldingProduct($product, [
            ['checkout', $this->checkoutPayload($product, 'New Buyer')],
            ['secure-stock', (string) $retrying->id],
        ]);

        [$checkout, $retry] = $results;
        $this->assertSame(200, $checkout['status'], json_encode($results));
        $this->assertSame(200, $retry['status'], json_encode($results));
        $this->assertTrue($retry['secured'], json_encode($results));

        $held = StockReservation::where('product_id', $product->id)->where('status', 'held')->sum('quantity');
        $this->assertSame(2, (int) $held);
        $this->assertSame(0, $product->fresh()->availableStock());
    }

    public function test_a_checkout_and_a_payment_settling_on_the_same_product_do_not_deadlock(): void
    {
        $product = $this->product(batchLimit: 2);
        $paying = $this->unpaidOrderHolding($product, 'held');

        $results = $this->whileHoldingProduct($product, [
            ['checkout', $this->checkoutPayload($product, 'New Buyer')],
            ['settle', (string) $paying->id],
        ]);

        [$checkout, $settle] = $results;
        $this->assertSame(200, $checkout['status'], json_encode($results));
        $this->assertSame(200, $settle['status'], json_encode($results));
        $this->assertTrue($settle['settled'], json_encode($results));

        $this->assertSame(1, $product->fresh()->batch_sold);
        $this->assertSame(0, $product->fresh()->availableStock());
    }

    public function test_a_checkout_that_loses_a_deadlock_retries_and_succeeds_once(): void
    {
        $product = $this->product(batchLimit: 3);
        // An unrelated hold, so the adversary has index records to gap-lock.
        $this->unpaidOrderHolding($this->product(batchLimit: 3, slug: 'other'), 'held');

        // Checkout takes the product; the adversary blocks new holds and then
        // wants the product: a deadlock that InnoDB resolves against checkout.
        $results = $this->whileHoldingProduct($product, [
            ['checkout', $this->checkoutPayload($product, 'Retry Buyer')],
            ['block-holds-then-product', (string) $product->id],
        ]);

        [$checkout, $adversary] = $results;
        $this->assertFalse($adversary['victim'] ?? true, json_encode($results));
        $this->assertSame(200, $checkout['status'], json_encode($results));
        $this->assertGreaterThanOrEqual(1, $checkout['rollbacks'], 'the first attempt should have been rolled back: ' . json_encode($results));

        // Exactly one order and one hold despite the retry.
        $orders = CustomOrder::where('full_name', 'Retry Buyer')->get();
        $this->assertCount(1, $orders);
        $holds = StockReservation::where('product_id', $product->id)->get();
        $this->assertCount(1, $holds);
        $this->assertSame($orders->first()->id, $holds->first()->custom_order_id);
        $this->assertSame(2, $product->fresh()->availableStock());
    }

    public function test_a_settlement_that_loses_a_deadlock_retries_and_settles_once(): void
    {
        $product = $this->product(batchLimit: 3);
        $paying = $this->unpaidOrderHolding($product, 'held');

        // Settlement claims the order and waits for the product; the adversary
        // locks the order's holds and then wants the claimed order row.
        $results = $this->whileHoldingProduct($product, [
            ['settle', (string) $paying->id],
            ['lock-holds-then-order', (string) $paying->id],
        ]);

        [$settle, $adversary] = $results;
        $this->assertFalse($adversary['victim'] ?? true, json_encode($results));
        $this->assertSame(200, $settle['status'], json_encode($results));
        $this->assertTrue($settle['settled'], json_encode($results));
        $this->assertGreaterThanOrEqual(1, $settle['rollbacks'], 'the first attempt should have been rolled back: ' . json_encode($results));
        $this->assertSame(1, $settle['paid_events'], 'one settlement event, after commit');

        $paying->refresh();
        $this->assertTrue($paying->is_paid);
        $this->assertSame(1, $product->fresh()->batch_sold, 'stock committed exactly once');
        $this->assertSame(['committed'], $paying->stockReservations()->pluck('status')->all());
    }

    /**
     * Lock the product row, start each worker in turn (waiting until it is
     * blocked on a lock before starting the next), release, collect results.
     *
     * @param  list<array{0: string, 1: string}>  $jobs
     * @return list<array<string, mixed>>
     */
    private function whileHoldingProduct(Product $product, array $jobs): array
    {
        $running = [];

        DB::beginTransaction();
        try {
            Product::whereKey($product->id)->lockForUpdate()->first();

            foreach ($jobs as $i => [$action, $arg]) {
                $running[] = $this->startWorker($action, $arg);
                $this->waitForLockWaits($i + 1, $running);
            }
        } finally {
            DB::commit();
        }

        return array_map(function (InvokedProcess $process) {
            $result = $process->wait();
            $decoded = json_decode(trim($result->output()), true);

            return is_array($decoded) ? $decoded : ['status' => 0, 'error' => $result->output() . $result->errorOutput()];
        }, $running);
    }

    private function startWorker(string $action, string $arg): InvokedProcess
    {
        $db = config('database.connections.' . config('database.default'));

        return Process::env([
            'APP_ENV' => 'testing',
            'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => config('database.default'),
            'DB_URL' => '',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'],
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'LOG_CHANNEL' => 'stderr',
            'MPESA_ENV' => 'sandbox',
            'MPESA_CONSUMER_KEY' => 'test-key',
            'MPESA_CONSUMER_SECRET' => 'test-secret',
            'MPESA_SHORTCODE' => '174379',
            'MPESA_PASSKEY' => 'test-passkey',
            'MPESA_CALLBACK_TOKEN' => 'test-callback-token',
        ])->timeout(60)->start([PHP_BINARY, base_path('tests/Concurrency/worker.php'), $action, $arg]);
    }

    /**
     * Wait until $count other connections are stuck on a statement (or a worker
     * exits early). Nothing these workers run takes 300ms unless it is queued
     * on a row lock. PROCESSLIST lists our own user's connections without the
     * PROCESS privilege; INNODB_TRX needs it and proved unreliable on 11.8.
     */
    private function waitForLockWaits(int $count, array $running): void
    {
        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline) {
            $waiting = (int) DB::scalar("SELECT COUNT(*) FROM information_schema.PROCESSLIST
                WHERE ID != CONNECTION_ID() AND COMMAND IN ('Query', 'Execute') AND INFO IS NOT NULL AND TIME_MS > 300");
            if ($waiting >= $count) {
                return;
            }
            foreach ($running as $process) {
                if (! $process->running()) {
                    $this->fail('A worker finished before reaching the lock: ' . $process->output() . $process->errorOutput());
                }
            }
            usleep(20_000);
        }
        $this->fail("Timed out waiting for {$count} worker(s) to queue on a lock. Connections: "
            . json_encode(DB::select('SELECT ID, COMMAND, STATE, INFO FROM information_schema.PROCESSLIST WHERE ID != CONNECTION_ID()'))
            . ' Worker output: ' . collect($running)->map(fn ($p) => $p->latestOutput() . $p->latestErrorOutput())->implode(' | '));
    }

    private function product(int $batchLimit, string $slug = 'last-hoodie'): Product
    {
        return Product::create([
            'name' => "Hoodie {$slug}", 'slug' => $slug, 'description' => 'One left.',
            'price' => 2500, 'in_stock' => true, 'batch_limit' => $batchLimit, 'batch_sold' => 0,
        ]);
    }

    private function unpaidOrderHolding(Product $product, string $holdStatus): CustomOrder
    {
        $order = CustomOrder::create([
            'full_name' => 'Earlier Buyer', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 2500, 'is_paid' => false,
        ]);
        $order->stockReservations()->create([
            'product_id' => $product->id, 'quantity' => 1, 'status' => $holdStatus,
            'expires_at' => $holdStatus === 'held' ? now()->addMinutes(15) : now()->subMinute(),
        ]);

        return $order;
    }

    private function checkoutPayload(Product $product, string $name): string
    {
        return json_encode([
            'full_name' => $name, 'email' => 'buyer@example.com', 'address' => '1 Moi Avenue', 'city' => 'Nairobi',
            'phone' => '0712345678', 'items' => [['id' => $product->id, 'quantity' => 1]],
        ]);
    }
}
