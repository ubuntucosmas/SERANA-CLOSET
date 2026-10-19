<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Batch 2 Phase A1: the ledger tables and the split status columns (design §2.1). */
class LedgerSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function order(): CustomOrder
    {
        return CustomOrder::create(['full_name' => 'A', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'price_quoted' => 5000]);
    }

    private function payment(CustomOrder $order, array $o = []): OrderPayment
    {
        return OrderPayment::create(array_merge([
            'custom_order_id' => $order->id, 'type' => OrderPayment::PAYMENT, 'amount' => 5000, 'method' => 'mpesa_manual',
            'external_reference' => 'QX1', 'actor_type' => 'system', 'occurred_at' => now(),
        ], $o));
    }

    public function test_orders_carry_the_split_status_and_money_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('custom_orders', [
            'kind', 'garment', 'channel', 'order_state', 'fulfilment_status', 'payment_status', 'resolution',
            'chargeable_total', 'amount_paid', 'balance_due',
        ]));
        $this->assertTrue(Schema::hasColumns('order_lines', ['custom_order_id', 'line_type', 'product_id', 'description', 'quantity', 'unit_amount', 'amount', 'actor_type', 'actor_id', 'reason', 'reverses_line_id', 'meta']));
        $this->assertTrue(Schema::hasColumns('order_payments', ['custom_order_id', 'type', 'amount', 'method', 'external_reference', 'payment_attempt_id', 'actor_type', 'actor_id', 'reason', 'occurred_at']));
    }

    public function test_new_orders_default_to_open_pending_unpaid_with_no_resolution(): void
    {
        $fresh = $this->order()->fresh();

        $this->assertSame('open', $fresh->order_state);
        $this->assertSame('pending', $fresh->fulfilment_status);
        $this->assertSame('unpaid', $fresh->payment_status);
        $this->assertSame('none', $fresh->resolution);
    }

    public function test_an_external_reference_is_booked_once_per_method(): void
    {
        $order = $this->order();
        $this->payment($order);
        $this->payment($order, ['method' => 'cash', 'external_reference' => 'QX1']); // another method may reuse it

        $this->expectException(QueryException::class);
        $this->payment($order); // same method + reference
    }

    public function test_payments_without_a_reference_are_not_blocked_by_the_unique_key(): void
    {
        $order = $this->order();
        $this->payment($order, ['method' => 'cash', 'external_reference' => null]);
        $this->payment($order, ['method' => 'cash', 'external_reference' => null]);

        $this->assertSame(2, $order->payments()->count());
    }

    public function test_one_stk_attempt_settles_at_most_once(): void
    {
        $order = $this->order();
        $attempt = $order->paymentAttempts()->create(['checkout_request_id' => 'ws_1', 'phone' => '254712345678', 'amount' => 5000, 'status' => 'paid']);
        $this->payment($order, ['method' => 'mpesa_stk', 'external_reference' => null, 'payment_attempt_id' => $attempt->id]);

        $this->expectException(QueryException::class);
        $this->payment($order, ['method' => 'mpesa_stk', 'external_reference' => null, 'payment_attempt_id' => $attempt->id]);
    }

    public function test_ledger_rows_and_lines_are_append_only(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $line = OrderLine::create(['custom_order_id' => $order->id, 'line_type' => 'item', 'description' => 'Set', 'quantity' => 1, 'unit_amount' => 5000, 'amount' => 5000, 'actor_type' => 'system']);

        foreach ([fn () => $payment->update(['amount' => 1]), fn () => $payment->delete(), fn () => $line->update(['amount' => 1]), fn () => $line->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('A ledger row or line was changed.');
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_order_with_money_on_it_cannot_be_deleted_from_the_database(): void
    {
        $order = $this->order();
        $this->payment($order);

        $this->expectException(QueryException::class);
        CustomOrder::withTrashed()->whereKey($order->id)->forceDelete();
    }
}
