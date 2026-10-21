<?php

/*
 * Runs one real app action in its own PHP process, with its own database
 * connection, so tests/Feature/ConcurrencyTest can race actions against each
 * other on MariaDB. Prints one JSON line: {"status": int, ...}.
 *
 *   php tests/Concurrency/worker.php checkout '<json checkout payload>'
 *   php tests/Concurrency/worker.php secure-stock <order id>
 *   php tests/Concurrency/worker.php settle <order id>
 *   php tests/Concurrency/worker.php pay-attempt <order id>:<attempt id>:<safaricom|system>
 *   php tests/Concurrency/worker.php settle-ref <order id>:<M-Pesa code>
 *   php tests/Concurrency/worker.php refund <order id>:<amount>:<code>
 *   php tests/Concurrency/worker.php count <variant id>:<location id>:<counted>   (a stock take, Phase D1)
 *   php tests/Concurrency/worker.php counter-sale <till id>:<seller id>:<product id>:<size>   (the till, Phase E)
 *   php tests/Concurrency/worker.php late-entry <till id>:<seller id>:<approver id>:<product id>:<size>:<slip>   (a paper slip, Phase E)
 *
 * Adversaries (test-only: take locks in the wrong order on purpose, to force
 * a deadlock on the app's first attempt). Each first writes throwaway rows so
 * InnoDB picks the app's lighter transaction as the deadlock victim:
 *   php tests/Concurrency/worker.php block-holds-then-product <product id>
 *   php tests/Concurrency/worker.php lock-order-then-product <order id>:<product id>
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
    } elseif ($action === 'stock-entry') {
        $app->make(ConsoleKernel::class)->bootstrap();
        $input = json_decode($arg, true, flags: JSON_THROW_ON_ERROR);
        try {
            $repeated = app(\App\Services\StockEntryService::class)->save(\App\Models\Product::findOrFail($input['product_id']), \App\Models\Location::findOrFail($input['location_id']), \App\Support\Actor::user(\App\Models\User::findOrFail($input['user_id'])), $input['entry']);
            echo json_encode(['status' => 200, 'repeated' => $repeated, 'rollbacks' => $rollbacks]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            echo json_encode(['status' => 422, 'errors' => $e->errors(), 'rollbacks' => $rollbacks]);
        }
    } elseif ($action === 'secure-stock') {
        $app->make(ConsoleKernel::class)->bootstrap();
        echo json_encode(['status' => 200, 'secured' => CustomOrder::findOrFail((int) $arg)->secureStockForPayment()]);
    } elseif ($action === 'settle') {
        $app->make(ConsoleKernel::class)->bootstrap();
        $order = CustomOrder::findOrFail((int) $arg);
        echo json_encode(['status' => 200, 'settled' => app(OrderSettlementService::class)->markPaid($order, 'RCPT-RACE'), 'rollbacks' => $rollbacks, 'paid_events' => $paidEvents]);
    } elseif ($action === 'pay-attempt') {
        // The callback (safaricom) or the reconciler (system) settling one STK attempt.
        [$orderId, $attemptId, $who] = explode(':', $arg);
        $app->make(ConsoleKernel::class)->bootstrap();
        $attempt = \App\Models\PaymentAttempt::findOrFail((int) $attemptId);
        $result = app(OrderSettlementService::class)->recordPayment(CustomOrder::findOrFail((int) $orderId), $attempt->amount, 'mpesa_stk',
            $who === 'system' ? \App\Support\Actor::system() : \App\Support\Actor::safaricom(), $who === 'system' ? null : 'RCPT-RACE', $attempt);
        echo json_encode(['status' => 200, 'booked' => $result['booked'], 'became_paid' => $result['became_paid'], 'paid_events' => $paidEvents]);
    } elseif ($action === 'settle-ref') {
        // A late payment with its own M-Pesa code (Phase C: never sold past the batch limit).
        [$orderId, $code] = explode(':', $arg);
        $app->make(ConsoleKernel::class)->bootstrap();
        $result = app(OrderSettlementService::class)->recordPayment(CustomOrder::findOrFail((int) $orderId), null, 'mpesa_stk', \App\Support\Actor::safaricom(), $code);
        echo json_encode(['status' => 200, 'booked' => $result['booked'], 'became_paid' => $result['became_paid'], 'stock_gone' => $result['stock_gone'], 'rollbacks' => $rollbacks]);
    } elseif ($action === 'refund') {
        // Mark refunded (Phase C), as two staff clicking at once would.
        [$orderId, $amount, $code] = explode(':', $arg);
        $app->make(ConsoleKernel::class)->bootstrap();
        try {
            app(OrderSettlementService::class)->recordRefund(CustomOrder::findOrFail((int) $orderId), (int) $amount, 'mpesa_manual', \App\Support\Actor::system(), $code, 'race');
            echo json_encode(['status' => 200, 'refunded' => true]);
        } catch (DomainException $e) {
            echo json_encode(['status' => 200, 'refunded' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'count') {
        [$variantId, $locationId, $counted] = array_map('intval', explode(':', $arg));
        $app->make(ConsoleKernel::class)->bootstrap();
        $owner = \App\Models\User::where('role', 'owner')->firstOrFail();
        app(\App\Services\StockService::class)->count(\App\Models\ProductVariant::findOrFail($variantId), \App\Models\Location::findOrFail($locationId),
            $counted, \App\Support\Actor::user($owner), 'Race: stock take');
        echo json_encode(['status' => 200, 'counted' => $counted, 'rollbacks' => $rollbacks]);
    } elseif ($action === 'counter-sale') {
        [$tillId, $sellerId, $productId, $size] = explode(':', $arg);
        $app->make(ConsoleKernel::class)->bootstrap();
        try {
            $order = app(\App\Services\CounterSaleService::class)->start(\App\Models\TillSession::findOrFail((int) $tillId), \App\Models\User::findOrFail((int) $sellerId),
                [['id' => (int) $productId, 'quantity' => 1, 'options' => ['top_size' => $size]]]);
            echo json_encode(['status' => 200, 'order' => $order->id, 'rollbacks' => $rollbacks]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            echo json_encode(['status' => 422, 'body' => ['message' => $e->getMessage()], 'rollbacks' => $rollbacks]);
        }
    } elseif ($action === 'late-entry') {
        [$tillId, $sellerId, $approverId, $productId, $size, $slip] = explode(':', $arg);
        $app->make(ConsoleKernel::class)->bootstrap();
        $product = \App\Models\Product::findOrFail((int) $productId);
        $order = app(\App\Services\ShopDeskService::class)->lateEntry(\App\Models\TillSession::findOrFail((int) $tillId), \App\Models\User::findOrFail((int) $sellerId),
            \App\Models\User::findOrFail((int) $approverId), $slip, now()->subHour(), 'Race: outage',
            [['id' => (int) $productId, 'quantity' => 1, 'options' => ['top_size' => $size]]], [['method' => 'cash', 'amount' => (int) $product->price]]);
        echo json_encode(['status' => 200, 'order' => $order->id, 'rollbacks' => $rollbacks]);
    } elseif ($action === 'block-holds-then-product') {
        // Gap-lock all of stock_reservations (blocks any new hold), then want the product.
        $app->make(ConsoleKernel::class)->bootstrap();
        DB::beginTransaction();
        $weigh();
        DB::select('SELECT id FROM stock_reservations WHERE custom_order_id > 0 FOR UPDATE');
        DB::select('SELECT id FROM products WHERE id = ? FOR UPDATE', [(int) $arg]);
        DB::rollBack();
        echo json_encode(['status' => 200, 'victim' => false]);
    } elseif ($action === 'lock-order-then-product') {
        // Against the global order (products, holds, then the order row): take
        // the order row first, then want the product settlement holds.
        [$orderId, $productId] = array_map('intval', explode(':', $arg));
        $app->make(ConsoleKernel::class)->bootstrap();
        DB::beginTransaction();
        $weigh();
        DB::select('SELECT id FROM custom_orders WHERE id = ? FOR UPDATE', [$orderId]);
        DB::select('SELECT id FROM products WHERE id = ? FOR UPDATE', [$productId]);
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
