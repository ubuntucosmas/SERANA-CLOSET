<?php

namespace Tests\Feature;

use App\Mail\WhatsAppOrderPlacedMail;
use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\OrderStatus;
use App\Support\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\StockFixtures; // runs the same in either stock model (Phase D1)

    private function admin(): User
    {
        return User::factory()->create(['role' => 'owner', 'name' => 'Owner']);
    }

    private function setPayments(bool $mpesa, bool $whatsapp, string $instructions = 'Send Money to 0712 345 678', int $hours = 48): void
    {
        PaymentSettings::save(['mpesa_enabled' => $mpesa, 'whatsapp_enabled' => $whatsapp, 'instructions' => $instructions, 'whatsapp_hold_hours' => $hours]);
    }

    private function checkout(array $extra = [], ?Product $product = null): \Illuminate\Testing\TestResponse
    {
        $product ??= $this->stockedProduct(3, ['name' => 'Hoodie', 'slug' => 'hoodie-' . Str::random(4), 'price' => 2500]);
        $size = $product->variants()->where('size', \App\Models\ProductVariant::ONE_SIZE)->exists() ? \App\Models\ProductVariant::ONE_SIZE : 'M';

        return $this->postJson(route('checkout.process'), array_merge([
            'request_id' => (string) Str::uuid(), 'full_name' => 'Wanjiru', 'email' => 'wanjiru@example.com',
            'phone' => '0712345678', 'address' => 'Moi Ave 1', 'city' => 'Nairobi',
            'items' => [['id' => $product->id, 'quantity' => 1, 'options' => ['top_size' => $size]]],
        ], $extra));
    }

    public function test_storefront_sees_which_methods_are_on(): void
    {
        $this->setPayments(true, true);
        $this->get(route('shop'))->assertInertia(fn (Assert $p) => $p->where('payments.mpesa', true)->where('payments.whatsapp', true)->where('payments.default', 'mpesa'));

        $this->setPayments(false, true);
        $this->get(route('shop'))->assertInertia(fn (Assert $p) => $p->where('payments.mpesa', false)->where('payments.default', 'whatsapp')
            ->where('payments.instructions', 'Send Money to 0712 345 678'));
    }

    public function test_without_credentials_whatsapp_is_offered_even_if_switched_off(): void
    {
        config(['services.mpesa.key' => null]);
        $this->setPayments(true, false);

        $this->assertFalse(PaymentSettings::mpesaAvailable());
        $this->assertSame([PaymentSettings::WHATSAPP], PaymentSettings::methods(), 'checkout never dead-ends');
    }

    public function test_whatsapp_checkout_records_a_real_order_with_a_long_hold_and_an_email(): void
    {
        Mail::fake();
        $this->setPayments(true, true, 'Pochi la Biashara 0712 345 678', 24);

        $res = $this->checkout(['payment_method' => 'whatsapp'])->assertOk()
            ->assertJsonPath('payment_method', 'whatsapp')
            ->assertJsonPath('instructions', 'Pochi la Biashara 0712 345 678')
            ->assertJsonPath('hold_hours', 24);

        $order = CustomOrder::findOrFail($res->json('order_id'));
        $this->assertSame('whatsapp', $order->payment_method);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertStringContainsString('wa.me', $res->json('whatsapp_url'));
        $this->assertStringContainsString(urlencode($order->cipher), $res->json('whatsapp_url'));
        $this->assertStringContainsString(rawurlencode('One size'), str_replace('+', '%20', $res->json('whatsapp_url')), 'the size, as a one-size piece reads');
        $this->assertStringNotContainsString(rawurlencode('Size One size'), str_replace('+', '%20', $res->json('whatsapp_url')));

        $hold = StockReservation::where('custom_order_id', $order->id)->firstOrFail();
        $this->assertTrue($hold->expires_at->greaterThan(now()->addHours(23)), 'WhatsApp holds last hours, not minutes');
        Mail::assertQueued(WhatsAppOrderPlacedMail::class, fn ($m) => $m->hasTo('wanjiru@example.com'));
    }

    public function test_a_switched_off_method_is_refused_at_checkout(): void
    {
        $this->setPayments(false, true);
        $this->checkout(['payment_method' => 'mpesa'])->assertStatus(422)->assertJsonValidationErrors('payment_method');
        // No method sent: falls back to what's on.
        $this->checkout()->assertOk()->assertJsonPath('payment_method', 'whatsapp');
    }

    public function test_pausing_mpesa_blocks_new_prompts_but_settles_payments_in_progress(): void
    {
        Event::fake([\App\Events\MpesaWebhookHandled::class]);
        $this->setPayments(true, true);
        $orderId = $this->checkout()->assertOk()->json('order_id');
        $order = CustomOrder::find($orderId);
        PaymentAttempt::create(['custom_order_id' => $orderId, 'checkout_request_id' => 'ws_CO_live_1', 'phone' => '254712345678', 'amount' => 2500, 'status' => PaymentAttempt::PENDING]);

        $this->setPayments(false, true);

        // New prompt: refused with a code the checkout understands.
        $this->postJson(route('mpesa.stk'), ['order_id' => $orderId, 'phone' => '0712345678'])
            ->assertStatus(409)->assertJsonPath('code', 'mpesa_paused')->assertJsonPath('whatsapp', true);

        // The prompt already sent still settles when Safaricom calls back.
        config(['services.mpesa.callback_token' => 'secret']);
        $this->postJson('/payments/mpesa/callback/secret', ['Body' => ['stkCallback' => [
            'MerchantRequestID' => 'm', 'CheckoutRequestID' => 'ws_CO_live_1', 'ResultCode' => 0, 'ResultDesc' => 'ok',
            'CallbackMetadata' => ['Item' => [['Name' => 'Amount', 'Value' => 2500], ['Name' => 'MpesaReceiptNumber', 'Value' => 'QK123']]],
        ]]])->assertOk();

        $this->assertTrue($order->fresh()->is_paid);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_an_mpesa_order_can_move_to_whatsapp_without_a_duplicate(): void
    {
        Mail::fake();
        $this->setPayments(true, true);
        $orderId = $this->checkout()->assertOk()->json('order_id');

        $this->postJson(route('checkout.whatsapp', $orderId))->assertOk()
            ->assertJsonPath('payment_method', 'whatsapp')
            ->assertJsonPath('reference', CustomOrder::find($orderId)->cipher);

        $this->assertDatabaseCount('custom_orders', 1);
        $this->assertSame('whatsapp', CustomOrder::find($orderId)->payment_method);
        $this->assertTrue(StockReservation::where('custom_order_id', $orderId)->first()->expires_at->greaterThan(now()->addHours(47)));
        Mail::assertQueued(WhatsAppOrderPlacedMail::class);
    }

    public function test_switching_is_refused_while_a_prompt_may_still_be_answered_and_for_strangers(): void
    {
        $this->setPayments(true, true);
        $orderId = $this->checkout()->assertOk()->json('order_id');
        PaymentAttempt::create(['custom_order_id' => $orderId, 'checkout_request_id' => 'ws_CO_x', 'phone' => '254712345678', 'amount' => 2500, 'status' => PaymentAttempt::PENDING]);
        $this->postJson(route('checkout.whatsapp', $orderId))->assertStatus(409);

        // A different browser session cannot touch someone else's order.
        $this->flushSession();
        $this->postJson(route('checkout.whatsapp', $orderId))->assertNotFound();
    }

    public function test_a_waiting_whatsapp_order_is_not_superseded_by_a_new_checkout(): void
    {
        $this->setPayments(true, true);
        $first = $this->checkout(['payment_method' => 'whatsapp'])->assertOk()->json('order_id');
        $this->checkout(['payment_method' => 'mpesa'])->assertOk();

        $this->assertSame(OrderStatus::PENDING_PAYMENT, CustomOrder::find($first)->status);
    }

    public function test_whatsapp_orders_outlive_the_mpesa_window_and_expire_after_their_own(): void
    {
        $this->setPayments(true, true, 'x', 12);
        // Uncapped product: no stock hold at all, which used to expire orders after 15 minutes.
        $product = Product::create(['name' => 'Tee', 'slug' => 'tee', 'price' => 1000, 'in_stock' => true, 'garment_type' => 'hoodie']);
        $id = $this->checkout(['payment_method' => 'whatsapp'], $product)->assertOk()->json('order_id');

        $this->travel(2)->hours();
        $this->artisan('stock:release-expired');
        $this->assertSame(OrderStatus::PENDING_PAYMENT, CustomOrder::find($id)->status);

        $this->travel(11)->hours();
        $this->artisan('stock:release-expired');
        $this->assertSame(OrderStatus::EXPIRED, CustomOrder::find($id)->status);
    }

    public function test_studio_confirms_a_whatsapp_payment_through_the_normal_settlement(): void
    {
        Event::fake([\App\Events\MpesaWebhookHandled::class]);
        $this->setPayments(false, true);
        $id = $this->checkout(['payment_method' => 'whatsapp'])->assertOk()->json('order_id');
        $order = CustomOrder::find($id);

        // Wrong amount: refused, nothing changes.
        $this->actingAs($this->admin())->post(route('studio.order.record_payment', $order), ['amount' => 2000, 'method' => 'mpesa_manual', 'reference' => 'QK9'])
            ->assertSessionHasErrors('amount');
        $this->assertFalse($order->fresh()->is_paid);

        $this->post(route('studio.order.record_payment', $order), ['amount' => 2500, 'method' => 'mpesa_manual', 'reference' => 'QK9XYZ'])->assertSessionHas('success');

        $order->refresh();
        $this->assertTrue($order->is_paid);
        $this->assertSame(OrderStatus::PAID, $order->status);
        $this->assertSame('QK9XYZ', $order->mpesa_receipt_number);
        $this->assertNotNull($order->paid_at);
        $this->assertStringContainsString('WhatsApp payment confirmed by Owner', $order->internal_notes);
        $this->assertSame(1, (int) Product::first()->batch_sold, 'stock committed exactly once');
        Event::assertDispatched(\App\Events\MpesaWebhookHandled::class); // → receipt email
    }

    public function test_mpesa_shop_orders_still_cannot_be_hand_marked_paid(): void
    {
        $this->setPayments(true, true);
        $order = CustomOrder::find($this->checkout()->assertOk()->json('order_id'));
        $this->actingAs($this->admin())->post(route('studio.order.record_payment', $order), ['amount' => 2500, 'reference' => 'X'])->assertSessionHas('error');
        $this->assertFalse($order->fresh()->is_paid);
    }

    public function test_studio_can_cancel_an_unpaid_whatsapp_order_and_release_its_stock(): void
    {
        $this->setPayments(false, true);
        $order = CustomOrder::find($this->checkout(['payment_method' => 'whatsapp'])->assertOk()->json('order_id'));

        $this->actingAs($this->admin())->post(route('studio.order.cancel', $order))->assertSessionHas('success');

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, StockReservation::where('custom_order_id', $order->id)->where('status', 'held')->count());
        // A cancelled order cannot then be confirmed by mistake.
        $this->post(route('studio.order.record_payment', $order), ['amount' => 2500, 'reference' => 'X'])->assertSessionHas('error');
    }

    public function test_tracking_shows_whatsapp_payment_instructions_until_paid(): void
    {
        $this->setPayments(false, true, 'Till 123456');
        $order = CustomOrder::find($this->checkout(['payment_method' => 'whatsapp'])->assertOk()->json('order_id'));

        $this->postJson(route('track.lookup'), ['reference' => $order->cipher, 'phone' => '0712345678'])->assertOk()
            ->assertJsonPath('order.payment_method', 'whatsapp')
            ->assertJsonPath('order.payment_instructions', 'Till 123456');
    }

    public function test_studio_payment_settings_rules(): void
    {
        $admin = $this->admin();
        $base = ['mpesa_enabled' => true, 'whatsapp_enabled' => true, 'instructions' => '', 'whatsapp_hold_hours' => 48];

        $this->actingAs($admin)->put(route('admin.payment_settings.update'), array_merge($base, ['mpesa_enabled' => false, 'whatsapp_enabled' => false]))
            ->assertSessionHasErrors('whatsapp_enabled');
        $this->put(route('admin.payment_settings.update'), array_merge($base, ['mpesa_enabled' => false]))
            ->assertSessionHasErrors('instructions');
        $this->put(route('admin.payment_settings.update'), array_merge($base, ['whatsapp_hold_hours' => 5]))
            ->assertSessionHasErrors('whatsapp_hold_hours');

        config(['services.mpesa.passkey' => null]);
        $this->put(route('admin.payment_settings.update'), $base)->assertSessionHasErrors('mpesa_enabled');

        $this->put(route('admin.payment_settings.update'), array_merge($base, ['mpesa_enabled' => false, 'instructions' => 'Send Money 0712']))
            ->assertSessionHasNoErrors();
        $this->assertSame(['whatsapp'], PaymentSettings::methods());

        // Customers can't change it.
        $this->actingAs(User::factory()->create(['role' => 'customer']))->put(route('admin.payment_settings.update'), $base)->assertRedirect();
    }

    public function test_studio_can_test_the_mpesa_connection(): void
    {
        Http::fake(['*oauth*' => Http::response(['access_token' => 'abc', 'expires_in' => 3599])]);
        $this->actingAs($this->admin())->postJson(route('admin.payment_settings.test_mpesa'))
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('env', 'sandbox');
    }
}
