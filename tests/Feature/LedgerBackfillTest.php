<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batch 2 Phase A2 (design §2.3): every legacy row maps to the new model, the
 * backfill is idempotent and reversible, and verify catches drift.
 *
 * Fixtures are inserted straight into the table (no model events), the way
 * old rows sit in the database today. One per combination seen in real data.
 */
class LedgerBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function legacy(array $row): int
    {
        return DB::table('custom_orders')->insertGetId(array_merge([
            'cipher' => 'SRN-' . strtoupper(bin2hex(random_bytes(4))) . '-2026',
            'full_name' => 'Legacy', 'whatsapp_number' => '254712345678', 'phone' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 0, 'is_paid' => false,
            'payment_method' => 'mpesa', 'created_at' => now(), 'updated_at' => now(),
        ], $row));
    }

    private function attempt(int $orderId, string $status, int $amount, ?string $receipt = null): void
    {
        DB::table('payment_attempts')->insert([
            'custom_order_id' => $orderId, 'checkout_request_id' => 'ws_' . bin2hex(random_bytes(4)), 'phone' => '254712345678',
            'amount' => $amount, 'status' => $status, 'mpesa_receipt_number' => $receipt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function items(array ...$lines): string
    {
        return json_encode(array_merge($lines, ['precision_sizing' => ['height_cm' => 170]]));
    }

    private function backfill(): void
    {
        $this->artisan('serana:ledger:backfill')->assertSuccessful();
    }

    public function test_every_legacy_combination_maps_to_the_new_model(): void
    {
        $paidShop = $this->legacy(['status' => 'Paid', 'is_paid' => true, 'price_quoted' => 7000,
            'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1], ['id' => 2, 'name' => 'Cap', 'price' => 1000, 'quantity' => 2])]);
        $this->attempt($paidShop, 'paid', 7000, 'RCPT1');
        $this->attempt($paidShop, 'failed', 7000);
        $expired = $this->legacy(['status' => 'Expired', 'price_quoted' => 5000, 'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $mismatch = $this->legacy(['status' => 'Amount Mismatch', 'price_quoted' => 5000, 'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $this->attempt($mismatch, 'failed', 5000);
        $waCancelled = $this->legacy(['status' => 'Cancelled', 'payment_method' => 'whatsapp', 'price_quoted' => 5000, 'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $waPaid = $this->legacy(['status' => 'Paid', 'is_paid' => true, 'payment_method' => 'whatsapp', 'price_quoted' => 5000, 'mpesa_receipt_number' => 'WA-REF', 'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $shipped = $this->legacy(['status' => 'shipped', 'is_paid' => true, 'price_quoted' => 5000, 'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $this->attempt($shipped, 'paid', 5000, 'RCPT2');
        $bespokeNew = $this->legacy(['type' => 'custom', 'status' => 'pending']);
        $bespokeLabel = $this->legacy(['type' => 'Hoodie & Jogger Set', 'status' => 'pending', 'price_quoted' => 8000]);
        $bespokeRecorded = $this->legacy(['type' => 'Gown', 'status' => 'sewing', 'is_paid' => true, 'price_quoted' => 12000, 'mpesa_receipt_number' => 'QK1']);
        $drop = $this->legacy(['type' => 'drop', 'status' => 'Paid', 'is_paid' => true, 'price_quoted' => 3500, 'items_json' => json_encode(['drop' => 'Night Run', 'size' => 'M'])]);
        $this->attempt($drop, 'paid', 3500, 'RCPT3');
        $failed = $this->legacy(['status' => 'Payment Failed', 'price_quoted' => 5000, 'mpesa_checkout_id' => 'ws_OLD',
            'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $archived = $this->legacy(['status' => 'completed', 'is_paid' => true, 'price_quoted' => 5000, 'deleted_at' => now(),
            'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $this->attempt($archived, 'paid', 5000, 'RCPT4');

        $this->backfill();

        $o = fn (int $id) => CustomOrder::withTrashed()->findOrFail($id);
        $expect = function (int $id, array $columns) use ($o) {
            $order = $o($id);
            foreach ($columns as $column => $value) {
                $this->assertSame($value, $order->{$column}, "order {$id} {$column}");
            }
        };

        $expect($paidShop, ['kind' => 'shop', 'channel' => 'online', 'order_state' => 'open', 'fulfilment_status' => 'pending',
            'payment_status' => 'paid', 'chargeable_total' => 7000, 'amount_paid' => 7000, 'balance_due' => 0]);
        $this->assertSame([['Set', 1, 5000], ['Cap', 2, 2000]], $o($paidShop)->lines()->get()->map(fn ($l) => [$l->description, $l->quantity, $l->amount])->all());
        $this->assertSame([['payment', 7000, 'mpesa_stk', 'RCPT1']], $o($paidShop)->payments()->get()->map(fn ($p) => [$p->type, $p->amount, $p->method, $p->external_reference])->all());
        $this->assertNotNull($o($paidShop)->payments()->first()->payment_attempt_id);

        $expect($expired, ['order_state' => 'expired', 'payment_status' => 'unpaid', 'amount_paid' => 0, 'balance_due' => 5000, 'payment_failed' => false]);
        $expect($mismatch, ['resolution' => 'amount_mismatch', 'payment_status' => 'amount_mismatch', 'amount_paid' => 0]);
        $this->assertSame(0, $o($mismatch)->payments()->count(), 'the amount actually paid was never stored');
        $expect($waCancelled, ['channel' => 'whatsapp', 'order_state' => 'cancelled', 'payment_status' => 'unpaid']);
        $expect($waPaid, ['channel' => 'whatsapp', 'payment_status' => 'paid']);
        $this->assertSame([['mpesa_manual', 'WA-REF', 'system']], $o($waPaid)->payments()->get()->map(fn ($p) => [$p->method, $p->external_reference, $p->actor_type])->all());
        $expect($shipped, ['fulfilment_status' => 'shipped', 'payment_status' => 'paid']);
        $expect($bespokeNew, ['kind' => 'bespoke', 'garment' => null, 'payment_status' => 'unpaid', 'chargeable_total' => 0]);
        $this->assertSame(0, $o($bespokeNew)->lines()->count(), 'no quote yet');
        $expect($bespokeLabel, ['kind' => 'bespoke', 'garment' => 'Hoodie & Jogger Set', 'chargeable_total' => 8000, 'payment_status' => 'unpaid']);
        $expect($bespokeRecorded, ['kind' => 'bespoke', 'garment' => 'Gown', 'fulfilment_status' => 'sewing', 'payment_status' => 'paid', 'amount_paid' => 12000]);
        $expect($drop, ['kind' => 'drop', 'payment_status' => 'paid', 'chargeable_total' => 3500]);
        $this->assertSame(['Drop: Night Run (M)'], $o($drop)->lines()->pluck('description')->all());
        $expect($archived, ['fulfilment_status' => 'completed', 'payment_status' => 'paid']);
        // A failed prompt is a fact only the legacy status holds for pre-attempt orders.
        $expect($failed, ['order_state' => 'open', 'payment_status' => 'unpaid', 'payment_failed' => true]);

        // The legacy columns are never touched.
        $this->assertSame('Paid', DB::table('custom_orders')->where('id', $paidShop)->value('status'));
        $this->assertSame('Hoodie & Jogger Set', DB::table('custom_orders')->where('id', $bespokeLabel)->value('type'));

        $this->artisan('serana:ledger:verify')->expectsOutputToContain('0 difference(s)')->assertSuccessful();
    }

    public function test_a_duplicate_paid_order_backfills_as_refund_due(): void
    {
        $id = $this->legacy(['status' => 'Paid', 'is_paid' => true, 'price_quoted' => 5000, 'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $this->attempt($id, 'paid', 5000, 'RCPT-A');
        $this->attempt($id, 'paid', 5000, 'RCPT-B');

        $this->backfill();

        $order = CustomOrder::findOrFail($id);
        $this->assertSame('refund_due', $order->payment_status);
        $this->assertSame(10000, $order->amount_paid);
        $this->assertSame(-5000, $order->balance_due);
        $this->artisan('serana:ledger:verify')->assertSuccessful();
    }

    /** Found in real data: one M-Pesa receipt on paid attempts of two orders. Booked, marked, and surfaced, never silently merged. */
    public function test_a_receipt_reused_across_orders_is_booked_marked_and_reported(): void
    {
        $first = $this->legacy(['status' => 'Paid', 'is_paid' => true, 'price_quoted' => 3000, 'items_json' => $this->items(['id' => 1, 'name' => 'Cap', 'price' => 3000, 'quantity' => 1])]);
        $second = $this->legacy(['status' => 'shipped', 'is_paid' => true, 'price_quoted' => 3000, 'items_json' => $this->items(['id' => 1, 'name' => 'Cap', 'price' => 3000, 'quantity' => 1])]);
        $this->attempt($first, 'paid', 3000, 'TJQA7PAID1');
        $this->attempt($second, 'paid', 3000, 'TJQA7PAID1');

        $this->artisan('serana:ledger:backfill')->expectsOutputToContain('1 payment reference(s) were already booked on another order and were marked for review')->assertSuccessful();

        $this->assertSame('TJQA7PAID1', OrderPayment::where('custom_order_id', $first)->value('external_reference'));
        $this->assertSame("TJQA7PAID1#dup-order-{$second}", OrderPayment::where('custom_order_id', $second)->value('external_reference'));
        $this->artisan('serana:ledger:verify')
            ->expectsOutputToContain('0 difference(s)')
            ->expectsOutputToContain("Review: 1 M-Pesa reference(s) booked on more than one order: TJQA7PAID1 (orders {$first}, {$second})")
            ->assertSuccessful();
    }

    public function test_items_that_do_not_add_up_get_a_flagged_adjustment_line(): void
    {
        $id = $this->legacy(['status' => 'Pending Payment', 'price_quoted' => 5200, 'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);

        $this->artisan('serana:ledger:backfill')->expectsOutputToContain('1 order(s) needed a backfill adjustment line')->assertSuccessful();

        $line = OrderLine::where('custom_order_id', $id)->where('line_type', 'adjustment')->sole();
        $this->assertSame(200, $line->amount);
        $this->assertSame('backfill: items did not add up to the stored total', $line->reason);
        $this->assertSame(5200, CustomOrder::findOrFail($id)->chargeable_total);
    }

    public function test_the_backfill_is_idempotent(): void
    {
        $id = $this->legacy(['status' => 'Paid', 'is_paid' => true, 'price_quoted' => 5000, 'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $this->attempt($id, 'paid', 5000, 'RCPT1');

        $this->backfill();
        $this->backfill();

        $this->assertSame(1, OrderLine::where('custom_order_id', $id)->count());
        $this->assertSame(1, OrderPayment::where('custom_order_id', $id)->count());
    }

    public function test_the_backfill_migration_reverses_cleanly(): void
    {
        $this->ledgerChecks = []; // builds drift on purpose
        $id = $this->legacy(['status' => 'Expired', 'price_quoted' => 5000, 'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $before = (array) DB::table('custom_orders')->where('id', $id)->first(['status', 'type', 'is_paid', 'price_quoted', 'payment_method']);
        $migration = require database_path('migrations/2026_10_19_000004_backfill_order_ledger.php');

        $migration->up();
        $this->assertSame('expired', CustomOrder::findOrFail($id)->order_state);

        $migration->down();
        $this->assertSame(0, DB::table('order_lines')->count());
        $this->assertSame(0, DB::table('order_payments')->count());
        $row = DB::table('custom_orders')->where('id', $id)->first();
        $this->assertNull($row->kind);
        $this->assertSame('open', $row->order_state);
        $this->assertSame('unpaid', $row->payment_status);
        $this->assertEquals($before, (array) DB::table('custom_orders')->where('id', $id)->first(['status', 'type', 'is_paid', 'price_quoted', 'payment_method']));
    }

    public function test_verify_reports_drift_between_legacy_and_new_columns(): void
    {
        $this->ledgerChecks = []; // builds drift on purpose
        $id = $this->legacy(['status' => 'Pending Payment', 'price_quoted' => 5000, 'items_json' => $this->items(['id' => 1, 'name' => 'Set', 'price' => 5000, 'quantity' => 1])]);
        $this->backfill();

        // A legacy write that bypassed the dual-write.
        DB::table('custom_orders')->where('id', $id)->update(['status' => 'Expired']);

        $this->artisan('serana:ledger:verify')->expectsOutputToContain('order_state')->expectsOutputToContain('1 difference(s)')->assertFailed();
    }

    public function test_new_orders_get_the_same_shape_when_they_are_created(): void
    {
        $shop = CustomOrder::create(['full_name' => 'A', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT,
            'payment_method' => 'whatsapp', 'price_quoted' => 6000, 'items_json' => ['x' => ['name' => 'Set', 'price' => 3000, 'quantity' => 2], 'precision_sizing' => ['bust_cm' => 90]]]);
        $bespoke = CustomOrder::create(['full_name' => 'B', 'whatsapp_number' => '254712345678', 'type' => 'Gown']);

        $shop->refresh();
        $this->assertSame(['shop', 'whatsapp', 'open', 'unpaid', 6000, 6000], [$shop->kind, $shop->channel, $shop->order_state, $shop->payment_status, $shop->chargeable_total, $shop->balance_due]);
        $this->assertSame([['Set', 2, 3000, 6000]], $shop->lines()->get()->map(fn ($l) => [$l->description, $l->quantity, $l->unit_amount, $l->amount])->all());
        $this->assertSame(['bespoke', 'Gown', 0], [$bespoke->fresh()->kind, $bespoke->fresh()->garment, $bespoke->lines()->count()]);
        $this->artisan('serana:ledger:verify')->assertSuccessful();
    }
}
