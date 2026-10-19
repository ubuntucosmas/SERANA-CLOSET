<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Support\OrderPolicy;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batch 2 Phase B (design B, I9, D15): a signed, expiring /pay link per order
 * shows the quote, what is paid and the balance, lets the customer pay the
 * deposit or the balance, and grants ownership for the STK endpoints only.
 */
class PayLinkTest extends TestCase
{
    use RefreshDatabase;

    private function bespoke(int $quote = 10000, array $o = []): CustomOrder
    {
        return CustomOrder::create(array_merge(['full_name' => 'Amina Otieno', 'email' => 'amina@example.com', 'whatsapp_number' => '0712345678',
            'type' => 'Gown', 'status' => 'pending', 'price_quoted' => $quote, 'address' => 'Secret St 1'], $o))->fresh();
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => Role::OWNER]);
    }

    public function test_the_studio_gets_a_pay_link_and_a_whatsapp_message_for_a_quoted_bespoke_order(): void
    {
        $order = $this->bespoke();

        $this->actingAs($this->owner())->get(route('studio.index', ['tab' => 'orders']))
            ->assertInertia(fn ($page) => $page->where('orders.data.0.pay_link', fn ($url) => str_contains($url, '/pay/' . $order->cipher) && str_contains($url, 'signature='))
                ->where('orders.data.0.pay_whatsapp_url', function ($url) use ($order) {
                    $text = urldecode((string) parse_url($url, PHP_URL_QUERY));

                    return str_starts_with($url, 'https://wa.me/254712345678?text=')
                        && str_contains($text, $order->cipher) && str_contains($text, 'KSh 10,000') && str_contains($text, 'KSh 5,000')
                        && str_contains($text, '/pay/' . $order->cipher) && str_contains($text, OrderPolicy::depositPolicy());
                }));
    }

    public function test_no_pay_link_before_a_quote_or_for_shop_orders(): void
    {
        $this->assertNull($this->bespoke(0)->pay_link);
        $shop = CustomOrder::create(['full_name' => 'S', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 5000]);
        $this->assertNull($shop->fresh()->pay_link);
    }

    public function test_the_pay_page_shows_the_quote_and_what_is_owed_without_private_details(): void
    {
        $order = $this->bespoke();

        $this->get($order->pay_link)->assertOk()->assertInertia(fn ($page) => $page->component('Pay')
            ->where('valid', true)
            ->where('order.reference', $order->cipher)
            ->where('order.first_name', 'Amina')
            ->where('order.total', 10000)
            ->where('order.amount_paid', 0)
            ->where('order.balance_due', 10000)
            ->where('order.deposit_due', 5000)
            ->where('order.deposit_policy', OrderPolicy::depositPolicy())
            ->where('order.lines', [['description' => 'Bespoke quote', 'amount' => 10000]])
            ->missing('order.email')->missing('order.address')->missing('order.whatsapp_number'));
    }

    public function test_opening_the_link_lets_this_browser_pay_for_this_order_only(): void
    {
        Http::fake(['*oauth*' => Http::response(['access_token' => 't']), '*processrequest*' => Http::response(['CheckoutRequestID' => 'ws_PAY'])]);
        $order = $this->bespoke();
        $other = $this->bespoke();

        $this->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678', 'pay' => 'deposit'])->assertNotFound();

        $this->get($order->pay_link)->assertOk();
        $this->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678', 'pay' => 'deposit'])->assertOk();
        $this->postJson(route('mpesa.stk'), ['order_id' => $other->id, 'phone' => '0712345678'])->assertNotFound();
    }

    public function test_a_tampered_or_expired_link_shows_nothing_about_the_order(): void
    {
        $order = $this->bespoke();
        $link = $order->pay_link;

        $this->get($link . 'x')->assertForbidden()->assertInertia(fn ($page) => $page->component('Pay')->where('valid', false)->missing('order'));

        $this->travel(OrderPolicy::payLinkDays() + 1)->days();
        $this->get($link)->assertForbidden()->assertInertia(fn ($page) => $page->where('valid', false)->missing('order'));
        $this->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678'])->assertNotFound();
    }

    public function test_the_link_lasts_as_long_as_the_policy_says(): void
    {
        OrderPolicy::save(['deposit_percent' => 50, 'pay_link_days' => 3, 'deposit_policy' => '']);
        $link = $this->bespoke()->pay_link;

        $this->travel(2)->days();
        $this->get($link)->assertOk();
        $this->travel(2)->days();
        $this->get($link)->assertForbidden();
    }

    public function test_a_paid_order_shows_as_paid_with_nothing_left_to_pay(): void
    {
        $order = $this->bespoke(5000);
        $this->actingAs($this->owner())->post(route('studio.order.record_payment', $order), ['amount' => 5000, 'method' => 'cash', 'reference' => 'c1']);
        auth()->logout();

        $this->get($order->fresh()->pay_link ?? $order->payLink())->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.balance_due', 0)->where('order.payment_status', 'paid')->where('order.can_pay', false));
    }
}
