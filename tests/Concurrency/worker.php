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
 * The parent test passes the database and app settings through the
 * environment; nothing here is meant to run outside the test suite.
 */

use App\Models\CustomOrder;
use App\Services\OrderSettlementService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';

[, $action, $arg] = $argv + [null, '', ''];

try {
    if ($action === 'checkout') {
        $kernel = $app->make(HttpKernel::class);
        $request = Request::create('/checkout/process', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $arg);
        $response = $kernel->handle($request);
        echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)]);
        $kernel->terminate($request, $response);
    } elseif ($action === 'secure-stock') {
        $app->make(ConsoleKernel::class)->bootstrap();
        echo json_encode(['status' => 200, 'secured' => CustomOrder::findOrFail((int) $arg)->secureStockForPayment()]);
    } elseif ($action === 'settle') {
        $app->make(ConsoleKernel::class)->bootstrap();
        $order = CustomOrder::findOrFail((int) $arg);
        echo json_encode(['status' => 200, 'settled' => app(OrderSettlementService::class)->markPaid($order, 'RCPT-RACE')]);
    } else {
        throw new InvalidArgumentException("Unknown action: {$action}");
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 500, 'error' => get_class($e).': '.$e->getMessage()]);
    exit(1);
}
