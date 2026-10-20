<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * serana:ledger-check compares every order's legacy money fields (is_paid,
 * price_quoted, paid_at, receipt number) with what the ledger derives
 * (amount_paid, balance_due, payment_status). It must pass before
 * LEDGER_READS is turned on in production.
 */
class LedgerCheckTest extends TestCase
{
    use RefreshDatabase;

    /** These tests corrupt rows on purpose. */
    protected array $ledgerChecks = [];

    private function paidShop(): CustomOrder
    {
        $order = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'phone' => '254712345678', 'type' => 'standard',
            'status' => 'Pending Payment', 'price_quoted' => 5000, 'items_json' => [['name' => 'Set', 'price' => 5000, 'quantity' => 1]]]);
        app(OrderSettlementService::class)->recordPayment($order, 5000, 'mpesa_stk', Actor::safaricom(), 'QREF1');

        return $order->fresh();
    }

    private function bespokeDeposit(): CustomOrder
    {
        $order = CustomOrder::create(['full_name' => 'Amina', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => 'designing', 'price_quoted' => 10000]);
        app(OrderSettlementService::class)->recordPayment($order, 5000, 'cash', Actor::system(), 'cash-1');

        return $order->fresh();
    }

    public function test_consistent_orders_pass(): void
    {
        $this->paidShop();
        $this->bespokeDeposit();

        $this->artisan('serana:ledger-check')->expectsOutputToContain('Ledger check passed: 2 order(s), 0 mismatch(es).')->assertSuccessful();
    }

    public function test_a_legacy_paid_flag_the_ledger_does_not_support_is_reported(): void
    {
        $order = $this->bespokeDeposit();
        DB::table('custom_orders')->where('id', $order->id)->update(['is_paid' => true, 'paid_at' => now()]);

        $this->artisan('serana:ledger-check')
            ->expectsOutputToContain("{$order->cipher}  is_paid: legacy true, ledger false (paid KSh 5,000 of KSh 10,000)")
            ->expectsOutputToContain('1 mismatch(es)')
            ->assertFailed();
    }

    public function test_a_price_that_differs_from_the_lines_is_reported(): void
    {
        $order = $this->paidShop();
        DB::table('custom_orders')->where('id', $order->id)->update(['price_quoted' => 5500]);

        $this->artisan('serana:ledger-check')->expectsOutputToContain("{$order->cipher}  price_quoted: legacy 5500, ledger 5000")->assertFailed();
    }

    public function test_a_paid_order_without_paid_at_and_an_unpaid_one_with_it_are_reported(): void
    {
        $paid = $this->paidShop();
        $deposit = $this->bespokeDeposit();
        DB::table('custom_orders')->where('id', $paid->id)->update(['paid_at' => null]);
        DB::table('custom_orders')->where('id', $deposit->id)->update(['paid_at' => now()]);

        $this->artisan('serana:ledger-check')
            ->expectsOutputToContain("{$paid->cipher}  paid_at: legacy none, ledger paid")
            ->expectsOutputToContain("{$deposit->cipher}  paid_at: legacy set, ledger never fully paid")
            ->assertFailed();
    }

    public function test_a_receipt_number_missing_from_the_ledger_is_reported(): void
    {
        $order = $this->paidShop();
        DB::table('custom_orders')->where('id', $order->id)->update(['mpesa_receipt_number' => 'QOTHER']);

        $this->artisan('serana:ledger-check')->expectsOutputToContain("{$order->cipher}  receipt QOTHER: legacy has it, ledger has no payment with that reference")->assertFailed();
    }

    public function test_stored_money_columns_that_drift_from_the_ledger_are_reported(): void
    {
        $order = $this->bespokeDeposit();
        DB::table('custom_orders')->where('id', $order->id)->update(['amount_paid' => 9000, 'payment_status' => 'paid']);

        $this->artisan('serana:ledger-check')
            ->expectsOutputToContain("{$order->cipher}  amount_paid: stored 9000, ledger 5000")
            ->expectsOutputToContain("{$order->cipher}  payment_status: stored paid, ledger deposit_paid")
            ->assertFailed();
    }

    public function test_archived_orders_are_checked_too(): void
    {
        $order = $this->paidShop();
        $order->delete();
        DB::table('custom_orders')->where('id', $order->id)->update(['is_paid' => false]);

        $this->artisan('serana:ledger-check')->expectsOutputToContain("{$order->cipher}  is_paid: legacy false, ledger true")->assertFailed();
    }

    public function test_preflight_blocks_ledger_reads_while_the_check_fails(): void
    {
        $order = $this->paidShop();
        DB::table('custom_orders')->where('id', $order->id)->update(['price_quoted' => 5500]);

        config(['serana.ledger_reads' => true]);
        $this->artisan('serana:preflight')
            ->expectsOutputToContain('Ledger matches the legacy order fields: LEDGER_READS is on but serana:ledger-check finds 1 mismatch(es)')
            ->assertFailed();

        config(['serana.ledger_reads' => false]);
        $this->artisan('serana:preflight')->doesntExpectOutputToContain('Ledger matches the legacy order fields');
    }
}
