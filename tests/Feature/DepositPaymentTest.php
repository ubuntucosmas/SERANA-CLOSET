<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batch 2 Phase B (design B, I2): a customer pays a bespoke deposit or the
 * balance by M-Pesa. They choose which; the server decides the amount from
 * the ledger. Bespoke payments never touch the production stage.
 */
class DepositPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mpesa.callback_token' => 'secret']);
        Http::fake([
            '*oauth/v1/generate*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            '*stkpush/v1/processrequest*' => Http::sequence()->push(['CheckoutRequestID' => 'ws_A'])->push(['CheckoutRequestID' => 'ws_B'])->push(['CheckoutRequestID' => 'ws_C']),
        ]);
    }

    private function bespoke(int $quote = 10000, string $status = 'pending'): CustomOrder
    {
        return CustomOrder::create(['full_name' => 'Client', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => $status, 'price_quoted' => $quote]);
    }

    private function push(CustomOrder $order, ?string $pay)
    {
        return $this->withSession(['serana.order_ids' => [$order->id]])
            ->postJson(route('mpesa.stk'), array_filter(['order_id' => $order->id, 'phone' => '0712345678', 'pay' => $pay, 'amount' => 1]));
    }

    private function pushedAmounts(): array
    {
        return Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->url(), 'processrequest'))->map(fn ($pair) => (int) $pair[0]['Amount'])->values()->all();
    }

    private function stkCallback(string $id, int $amount, string $receipt, int $code = 0)
    {
        return $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => array_filter([
            'ResultCode' => $code, 'CheckoutRequestID' => $id, 'ResultDesc' => $code ? 'Cancelled' : null,
            'CallbackMetadata' => $code ? null : ['Item' => [['Name' => 'Amount', 'Value' => $amount], ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt]]],
        ], fn ($v) => $v !== null)]])->assertOk();
    }

    private function record(CustomOrder $order, int $amount): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::OWNER]))
            ->post(route('studio.order.record_payment', $order), ['amount' => $amount, 'method' => 'cash', 'reference' => 'cash-' . $amount]);
        auth()->logout();
    }

    public function test_the_customer_chooses_deposit_or_balance_and_the_server_sets_the_amount(): void
    {
        $order = $this->bespoke(10000);

        $this->push($order, 'deposit')->assertOk();
        $this->push($order, 'balance')->assertOk();
        $this->push($order, null)->assertOk(); // no choice: the balance

        $this->assertSame([5000, 10000, 10000], $this->pushedAmounts(), 'never the client\'s amount of 1');
        $this->assertSame([5000, 10000, 10000], $order->paymentAttempts()->orderBy('id')->pluck('amount')->all());
    }

    public function test_the_deposit_choice_asks_only_for_what_is_left_of_it(): void
    {
        $order = $this->bespoke(10000);
        $this->record($order, 1000);

        $this->push($order, 'deposit')->assertOk();
        $this->record($order, 4000); // deposit now fully paid
        $this->push($order->fresh(), 'deposit')->assertOk();

        $this->assertSame([4000, 5000], $this->pushedAmounts(), 'after the deposit, "deposit" means the balance');
    }

    public function test_shop_orders_have_no_deposit_option(): void
    {
        $shop = CustomOrder::create(['full_name' => 'S', 'whatsapp_number' => '254712345678', 'phone' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000]);

        $this->push($shop, 'deposit')->assertOk();

        $this->assertSame([5000], $this->pushedAmounts());
    }

    public function test_a_paid_deposit_is_booked_and_the_stage_is_untouched(): void
    {
        $order = $this->bespoke(10000, 'pending');
        $this->push($order, 'deposit');

        $this->stkCallback('ws_A', 5000, 'RDEP1');

        $fresh = $order->fresh();
        $this->assertSame(['pending', 'deposit_paid', 5000, 5000, false], [$fresh->status, $fresh->payment_status, $fresh->amount_paid, $fresh->balance_due, $fresh->is_paid]);
        $this->assertSame(['RDEP1'], $order->payments()->pluck('external_reference')->all());
    }

    public function test_a_retry_or_a_failed_prompt_never_moves_a_bespoke_order_into_payment_states(): void
    {
        $order = $this->bespoke(10000, 'designing');
        $this->record($order, 5000);

        $this->push($order->fresh(), 'balance')->assertOk();
        $this->assertSame('designing', $order->fresh()->status, 'sending a prompt keeps the stage');

        $this->stkCallback('ws_A', 0, '', 1032);
        $this->assertSame('designing', $order->fresh()->status, 'a cancelled prompt keeps the stage');
    }

    public function test_a_bespoke_payment_of_a_different_amount_is_booked_as_received(): void
    {
        $order = $this->bespoke(10000);
        $this->push($order, 'deposit');

        $this->stkCallback('ws_A', 3000, 'RSHORT');

        $fresh = $order->fresh();
        $this->assertSame(['pending', 'none', 'deposit_paid', 3000], [$fresh->status, $fresh->resolution, $fresh->payment_status, $fresh->amount_paid]);
        $this->assertStringContainsString('KSh 5000', $order->payments()->value('reason'));
    }

    public function test_status_polling_reports_the_attempt_and_the_balance(): void
    {
        $order = $this->bespoke(10000);
        $this->push($order, 'deposit');

        $pending = $this->withSession(['serana.order_ids' => [$order->id]])->getJson(route('mpesa.status', $order->id) . '?attempt=ws_A')->assertOk();
        $pending->assertJson(['attempt_status' => 'pending', 'amount_paid' => 0, 'balance_due' => 10000, 'payment_status' => 'unpaid']);

        $this->stkCallback('ws_A', 5000, 'RDEP1');

        $this->withSession(['serana.order_ids' => [$order->id]])->getJson(route('mpesa.status', $order->id) . '?attempt=ws_A')
            ->assertJson(['attempt_status' => 'paid', 'amount_paid' => 5000, 'balance_due' => 5000, 'payment_status' => 'deposit_paid', 'is_paid' => false]);
    }
}
