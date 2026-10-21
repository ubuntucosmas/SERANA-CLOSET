<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\OrderPayment;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\OrderStatus;
use App\Support\PaymentSettings;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * V1 money invariant 2: an order with money received is never superseded,
 * expired, cancelled or archived as an abandoned unpaid order. It keeps its
 * holds and its balance link, and if the balance never comes, what was
 * received becomes a refund the Studio can see (CustomOrder::unfunded()).
 *
 * Each case checks the database afterwards: the order, its holds, the stock
 * left, the ledger and the Studio lists, not only the HTTP answer.
 */
class MoneyNeverAbandonedTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\DeliveryFixtures;
    use \Tests\Concerns\StockFixtures; // the same rules in either stock model

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.mpesa.callback_token' => 'secret', 'services.store.notification_email' => 'owner@serana.test']);
        $this->product = $this->stockedProduct(3, ['name' => 'Linen Set', 'slug' => 'linen-set', 'price' => 5000]);
    }

    /** A checkout from this test's browser session; returns the order. */
    private function checkout(): CustomOrder
    {
        $json = $this->postJson(route('checkout.process'), [
            'request_id' => (string) Str::uuid(), 'full_name' => 'Jane Wanjiru', 'email' => 'jane@example.com', 'phone' => '0712345678',
            'address' => '1 Moi Avenue', 'city' => 'Nairobi', 'delivery_option_id' => $this->freeDelivery(),
            'items' => [['id' => $this->product->id, 'quantity' => 1, 'options' => ['top_size' => 'One size']]],
        ])->assertOk()->json();

        return CustomOrder::findOrFail($json['order_id']);
    }

    private function prompt(CustomOrder $order, string $id, int $amount): PaymentAttempt
    {
        return $order->paymentAttempts()->create(['checkout_request_id' => $id, 'phone' => '254712345678', 'amount' => $amount, 'status' => PaymentAttempt::PENDING]);
    }

    private function stkCallback(string $id, int $paid, string $receipt, int $code = 0): void
    {
        $body = ['ResultCode' => $code, 'CheckoutRequestID' => $id];
        if ($code === 0) {
            $body['CallbackMetadata'] = ['Item' => [['Name' => 'Amount', 'Value' => $paid], ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt]]];
        }
        $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => $body]])->assertOk();
    }

    /** A shop order paid KSh 4,000 of 5,000 by M-Pesa: a balance of 1,000 is owed. */
    private function partPaid(): CustomOrder
    {
        $order = $this->checkout();
        $this->prompt($order, 'ws_SHORT', 5000);
        $this->stkCallback('ws_SHORT', 4000, 'QSHORT1');

        $order = $order->fresh();
        $this->assertSame(['deposit_paid', 4000, 1000], [$order->payment_status, $order->amount_paid, $order->balance_due]);

        return $order;
    }

    /** What the ledger holds for an order: [type, amount, reference] per row. */
    private function ledger(CustomOrder $order): array
    {
        return OrderPayment::where('custom_order_id', $order->id)->orderBy('id')->get()
            ->map(fn ($p) => [$p->type, $p->amount, $p->external_reference])->all();
    }

    private function studio(string $tab): array
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);

        return $this->actingAs($owner)->get(route('studio.index', ['tab' => $tab]))->viewData('page')['props'];
    }

    // ---- Checkout supersede (the P1) ----

    /** A: nothing paid, nothing in flight: a new checkout closes it and frees its unit. */
    public function test_an_unpaid_abandoned_order_is_superseded(): void
    {
        $first = $this->checkout();
        $second = $this->checkout();

        $this->assertSame([OrderStatus::EXPIRED, 'expired'], [$first->fresh()->status, $first->fresh()->order_state]);
        $this->assertSame('released', $first->stockReservations()->sole()->status);
        $this->assertSame('held', $second->stockReservations()->sole()->status);
        $this->assertSame(2, $this->unitsLeft($this->product), 'only the new order holds a unit');
        $this->assertSame([], $this->ledger($first));
    }

    /** B, D, G, H: part-paid (a balance due) survives a new checkout with its hold and ledger intact. */
    public function test_a_part_paid_order_is_never_superseded(): void
    {
        $order = $this->partPaid();
        $ledger = $this->ledger($order);
        $hold = $order->stockReservations()->sole();
        $holdUntil = $hold->expires_at;

        $second = $this->checkout();

        $fresh = $order->fresh();
        $this->assertSame(['open', 'deposit_paid', 4000, 1000, OrderStatus::PENDING_PAYMENT],
            [$fresh->order_state, $fresh->payment_status, $fresh->amount_paid, $fresh->balance_due, $fresh->status]);
        $this->assertSame('held', $hold->fresh()->status, 'its unit is still held for the balance');
        $this->assertEquals($holdUntil, $hold->fresh()->expires_at, 'the balance hold is untouched');
        $this->assertSame($ledger, $this->ledger($order), 'the ledger is unchanged');
        $this->assertNotNull($fresh->pay_link, 'the customer can still pay the balance');
        $this->assertNotSame($order->id, $second->id);
        $this->assertSame(1, $this->unitsLeft($this->product), 'one unit each for the two orders, one left');
    }

    /** C: a fully paid order is never touched by a new checkout. */
    public function test_a_paid_order_is_never_superseded(): void
    {
        $order = $this->checkout();
        $this->prompt($order, 'ws_FULL', 5000);
        $this->stkCallback('ws_FULL', 5000, 'QFULL1');
        $ledger = $this->ledger($order);

        $this->checkout();

        $fresh = $order->fresh();
        $this->assertSame([OrderStatus::PAID, 'open', 'paid'], [$fresh->status, $fresh->order_state, $fresh->payment_status]);
        $this->assertSame('committed', $order->stockReservations()->sole()->status);
        $this->assertSame($ledger, $this->ledger($order));
    }

    /** E: an order waiting on a Studio decision (here an M-Pesa code already on another order) is kept. */
    public function test_an_order_awaiting_a_payment_decision_is_never_superseded(): void
    {
        $other = CustomOrder::create(['full_name' => 'Other', 'whatsapp_number' => '254700000001', 'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 100]);
        $order = $this->checkout();
        app(OrderSettlementService::class)->flagReferenceConflict($order, 'QCONFLICT', $other);

        $this->checkout();

        $fresh = $order->fresh();
        $this->assertSame(['open', 'needs_resolution'], [$fresh->order_state, $fresh->resolution]);
        $this->assertContains($order->cipher, collect($this->studio('home')['attention']['payments'])->pluck('reference')->all());
    }

    /** F: after a new checkout the part-paid order still takes its balance by M-Pesa and settles once. */
    public function test_the_part_paid_order_still_takes_its_balance_after_a_new_checkout(): void
    {
        $order = $this->partPaid();
        $this->checkout();

        Http::fake([
            '*oauth/v1/generate*' => Http::response(['access_token' => 'fake']),
            '*stkpush/v1/processrequest*' => Http::response(['CheckoutRequestID' => 'ws_BALANCE']),
        ]);
        $this->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678'])->assertOk();
        $this->assertSame(1000, PaymentAttempt::where('checkout_request_id', 'ws_BALANCE')->value('amount'), 'the prompt asks for the balance only');
        $this->stkCallback('ws_BALANCE', 1000, 'QBAL1');

        $fresh = $order->fresh();
        $this->assertSame([true, 'paid', 5000, 0], [$fresh->is_paid, $fresh->payment_status, $fresh->amount_paid, $fresh->balance_due]);
        $this->assertSame('committed', $order->stockReservations()->sole()->status);
        $this->assertSame(1, (int) $this->product->fresh()->batch_sold, 'sold once');
    }

    /** I: the balance never comes: the order expires into the Refunds queue, visible to the Studio. */
    public function test_an_unpaid_balance_becomes_a_visible_refund_not_a_silent_expiry(): void
    {
        $order = $this->partPaid();
        $this->checkout();

        $this->travel(25)->hours();
        $this->artisan('stock:release-expired')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame(['expired', 4000], [$fresh->order_state, -$fresh->balance_due], 'what was received is owed back');
        $this->assertSame('released', $order->stockReservations()->sole()->status);
        $row = collect($this->studio('refunds')['refunds'])->firstWhere('reference', $order->cipher);
        $this->assertSame(4000, $row['owed'] ?? null);
    }

    // ---- The same rule on every other path that closes an order ----

    /** A part-paid order can't be moved to WhatsApp, where its balance would have no path. */
    public function test_a_part_paid_order_cannot_switch_to_whatsapp(): void
    {
        $order = $this->partPaid();

        $this->postJson(route('checkout.whatsapp', $order))->assertStatus(409);

        $this->assertSame([PaymentSettings::MPESA, 'open'], [$order->fresh()->payment_method, $order->fresh()->order_state]);
    }

    /** A WhatsApp order with money recorded on it is neither expired nor cancelled as unpaid. */
    public function test_a_part_paid_whatsapp_order_is_not_expired_or_cancelled(): void
    {
        $order = CustomOrder::create(['full_name' => 'Jane Wanjiru', 'email' => 'jane@example.com', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000, 'payment_method' => PaymentSettings::WHATSAPP,
            'items_json' => [['id' => $this->product->id, 'name' => 'Linen Set', 'price' => 5000, 'quantity' => 1]]]);
        $this->holdFor($order, $this->product, 1, 'held', now()->addHours(48));
        app(OrderSettlementService::class)->recordPayment($order, 2000, 'cash', Actor::system(), 'CASH-1');
        $ledger = $this->ledger($order);

        $owner = User::factory()->create(['role' => Role::OWNER]);
        $this->actingAs($owner)->post(route('studio.order.cancel', $order))->assertSessionHas('error');
        $this->actingAs($owner)->delete(route('studio.order.destroy', $order))->assertSessionHas('error');
        $this->assertSame(['open', null], [$order->fresh()->order_state, $order->fresh()->deleted_at]);

        $this->travel(100)->hours(); // past every WhatsApp hold window
        $this->artisan('stock:release-expired')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame($ledger, $this->ledger($order));
        $this->assertSame(2000, -$fresh->balance_due, 'not silently expired: the 2,000 received is owed back');
        $this->assertContains($order->cipher, collect($this->studio('refunds')['refunds'])->pluck('reference')->all());
    }

    /** An unpaid WhatsApp order still expires and can still be cancelled. */
    public function test_an_unpaid_whatsapp_order_still_expires_and_cancels(): void
    {
        $make = fn () => CustomOrder::create(['full_name' => 'Jane', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'standard',
            'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000, 'payment_method' => PaymentSettings::WHATSAPP]);
        $cancelled = $make();
        $owner = User::factory()->create(['role' => Role::OWNER]);
        $this->actingAs($owner)->post(route('studio.order.cancel', $cancelled))->assertSessionHas('success');
        $this->assertSame('cancelled', $cancelled->fresh()->order_state);

        $stale = $make();
        $this->travel(100)->hours();
        $this->artisan('stock:release-expired')->assertSuccessful();
        $this->assertSame('expired', $stale->fresh()->order_state);
    }

    // ---- Reconciliation: a second payment whose callback was lost (P2) ----

    private function fakeQuery(string $code): void
    {
        Http::fake([
            '*oauth/v1/generate*' => Http::response(['access_token' => 'fake']),
            '*stkpushquery/v1/query*' => Http::response(['ResultCode' => $code, 'ResultDesc' => 'x']),
        ]);
    }

    public function test_a_second_paid_prompt_on_a_paid_order_is_found_and_owed_back(): void
    {
        $order = $this->checkout();
        $this->prompt($order, 'ws_ONE', 5000);
        $this->stkCallback('ws_ONE', 5000, 'QONE');
        $second = $this->prompt($order, 'ws_TWO', 5000);
        $second->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $this->fakeQuery('0');

        $this->artisan('mpesa:reconcile')->assertSuccessful();
        $this->artisan('mpesa:reconcile')->assertSuccessful(); // a second run books nothing more

        $fresh = $order->fresh();
        $this->assertSame([true, 10000, -5000, 'Paid twice'], [$fresh->is_paid, $fresh->amount_paid, $fresh->balance_due, $fresh->refund_cause]);
        $this->assertSame(PaymentAttempt::PAID, $second->fresh()->status);
        $this->assertSame(1, (int) $this->product->fresh()->batch_sold, 'stock sold once');
        $this->assertSame(5000, collect($this->studio('refunds')['refunds'])->firstWhere('reference', $order->cipher)['owed'] ?? null);
    }

    /** Invariant 3: once settled, a failed or cancelled prompt never makes the order unpaid or frees its stock. */
    public function test_a_failed_callback_never_unsettles_a_paid_order(): void
    {
        $order = $this->checkout();
        $this->prompt($order, 'ws_ONE', 5000);
        $this->prompt($order, 'ws_TWO', 5000);
        $this->stkCallback('ws_ONE', 5000, 'QONE');
        $ledger = $this->ledger($order);

        $this->stkCallback('ws_TWO', 0, '', 1032);

        $fresh = $order->fresh();
        $this->assertSame([true, OrderStatus::PAID, 'paid', 0], [$fresh->is_paid, $fresh->status, $fresh->payment_status, $fresh->balance_due]);
        $this->assertSame('committed', $order->stockReservations()->sole()->status);
        $this->assertSame($ledger, $this->ledger($order));
    }

    public function test_an_unanswered_second_prompt_on_a_paid_order_just_fails(): void
    {
        $order = $this->checkout();
        $this->prompt($order, 'ws_ONE', 5000);
        $this->stkCallback('ws_ONE', 5000, 'QONE');
        $second = $this->prompt($order, 'ws_TWO', 5000);
        $second->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $this->fakeQuery('1032');

        $this->artisan('mpesa:reconcile')->assertSuccessful();

        $this->assertSame(PaymentAttempt::FAILED, $second->fresh()->status);
        $this->assertSame([true, OrderStatus::PAID, 0], [$order->fresh()->is_paid, $order->fresh()->status, $order->fresh()->balance_due]);
    }
}
