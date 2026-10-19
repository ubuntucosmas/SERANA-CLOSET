<?php

/*
 * Runs one real app action in its own PHP process, with its own database
 * connection, so tests/Feature/ConcurrencyTest can race actions against each
 * other on MariaDB. Prints one JSON line: {"status": int, ...}.
 *
 *   php tests/Concurrency/worker.php checkout '<json checkout payload>'
 *   php tests/Concurrency/worker.php secure-stock <order id>
 *   php tests/Concurrency/worker.php settle <order id>
 *
 * Adversaries (test-only: take locks in the wrong order on purpose, to force
 * a deadlock on the app's first attempt). Each first writes throwaway rows so
 * InnoDB picks the app's lighter transaction as the deadlock victim:
 *   php tests/Concurrency/worker.php block-holds-then-product <product id>
 *   php tests/Concurrency/worker.php lock-holds-then-order <order id>
 *
 * App actions also report how many transaction rollbacks they went through.
 * The parent test passes the database and app settings through the
 * environment; nothing here is meant to run outside the test suite.
 */

use App\Models\CustomOrder;
use App\Services\OrderSettlementService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';

[, $action, $arg] = $argv + [null, '', ''];

$rollbacks = 0;
$app['events']->listen(TransactionRolledBack::class, function () use (&$rollbacks) {
    $rollbacks++;
});
$paidEvents = 0;
$app['events']->listen(\App\Events\MpesaWebhookHandled::class, function () use (&$paidEvents) {
    $paidEvents++;
});

/** Throwaway writes that make this transaction "heavier" than the app's. */
$weigh = function () {
    DB::table('leads')->insert(array_map(fn ($i) => [
        'email' => "adversary-{$i}@example.test", 'created_at' => now(), 'updated_at' => now(),
    ], range(1, 300)));
};

try {
    if ($action === 'checkout') {
        $kernel = $app->make(HttpKernel::class);
        $request = Request::create('/checkout/process', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $arg);
        $response = $kernel->handle($request);
        echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true), 'rollbacks' => $rollbacks]);
        $kernel->terminate($request, $response);
    } elseif ($action === 'secure-stock') {
        $app->make(ConsoleKernel::class)->bootstrap();
        echo json_encode(['status' => 200, 'secured' => CustomOrder::findOrFail((int) $arg)->secureStockForPayment()]);
    } elseif ($action === 'settle') {
        $app->make(ConsoleKernel::class)->bootstrap();
        $order = CustomOrder::findOrFail((int) $arg);
        echo json_encode(['status' => 200, 'settled' => app(OrderSettlementService::class)->markPaid($order, 'RCPT-RACE'), 'rollbacks' => $rollbacks, 'paid_events' => $paidEvents]);
    } elseif ($action === 'block-holds-then-product') {
        // Gap-lock all of stock_reservations (blocks any new hold), then want the product.
        $app->make(ConsoleKernel::class)->bootstrap();
        DB::beginTransaction();
        $weigh();
        DB::select('SELECT id FROM stock_reservations WHERE custom_order_id > 0 FOR UPDATE');
        DB::select('SELECT id FROM products WHERE id = ? FOR UPDATE', [(int) $arg]);
        DB::rollBack();
        echo json_encode(['status' => 200, 'victim' => false]);
    } elseif ($action === 'lock-holds-then-order') {
        // Lock the order's holds, then want the order row settlement has claimed.
        $app->make(ConsoleKernel::class)->bootstrap();
        DB::beginTransaction();
        $weigh();
        DB::select('SELECT id FROM stock_reservations WHERE custom_order_id = ? FOR UPDATE', [(int) $arg]);
        DB::select('SELECT id FROM custom_orders WHERE id = ? FOR UPDATE', [(int) $arg]);
        DB::rollBack();
        echo json_encode(['status' => 200, 'victim' => false]);
    } else {
        throw new InvalidArgumentException("Unknown action: {$action}");
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 500, 'error' => get_class($e).': '.$e->getMessage(), 'rollbacks' => $rollbacks,
        'victim' => str_contains($e->getMessage(), 'Deadlock')]);
    exit(1);
}
