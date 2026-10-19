<?php

namespace Tests\Feature;

use App\Mail\NewCustomOrderAlertMail;
use App\Models\Category;
use App\Models\CustomOrder;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Support\OrderStatus;
use App\Support\StudioWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The storefront and the Studio must tell the same story: what a shopper can
 * buy, what the owner sees as low stock, and which orders need a decision.
 */
class StorefrontStudioSyncTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $limit = 3, int $sold = 0): Product
    {
        return Product::create([
            'name' => 'Limited Set ' . uniqid(), 'slug' => 'limited-set-' . uniqid(), 'description' => 'A set',
            'price' => 5000, 'batch_limit' => $limit, 'batch_sold' => $sold, 'in_stock' => true,
            'image_path' => 'products/a.webp',
        ]);
    }

    private function order(array $attrs = []): CustomOrder
    {
        return CustomOrder::forceCreate(array_merge(['full_name' => 'Client', 'whatsapp_number' => '254700000000', 'phone' => '254700000000',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000, 'is_paid' => false], $attrs));
    }

    private function hold(Product $product, int $qty, array $orderAttrs = []): CustomOrder
    {
        $order = $this->order($orderAttrs);
        $order->stockReservations()->create(['product_id' => $product->id, 'quantity' => $qty, 'status' => 'held', 'expires_at' => now()->addHours(48)]);

        return $order;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    public function test_a_piece_held_by_an_unpaid_order_shows_sold_out_in_the_shop(): void
    {
        $product = $this->product(limit: 3, sold: 1);
        $this->hold($product, 2, ['payment_method' => 'whatsapp']);

        $this->get(route('shop'))->assertInertia(fn (Assert $page) => $page
            ->where('products.0.available_stock', 0));
        $this->get(route('shop.show', $product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('product.available_stock', 0));
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('featuredProducts.0.available_stock', 0));
    }

    public function test_listing_availability_does_not_query_per_product(): void
    {
        foreach (range(1, 6) as $i) $this->hold($this->product(limit: 5), 1);

        \DB::enableQueryLog();
        $this->get(route('shop'))->assertOk();
        $reservationQueries = collect(\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'stock_reservations'))->count();

        $this->assertSame(1, $reservationQueries, 'holds are summed in the product query, not once per product');
    }

    public function test_studio_stock_alert_and_table_count_unpaid_holds(): void
    {
        $product = $this->product(limit: 5, sold: 1);
        $this->hold($product, 2);

        $this->actingAs($this->admin())->get(route('studio.index'))->assertInertia(fn (Assert $page) => $page
            ->where('products.0.available_stock', 2)
            ->where('products.0.reserved_quantity', 2)
            ->where('attention.low_stock.0.id', $product->id)
            ->where('attention.low_stock.0.note', '2 left · 2 on hold awaiting payment'));
    }

    public function test_abandoned_failed_mpesa_checkouts_leave_the_payment_problems_queue(): void
    {
        $abandoned = $this->order(['status' => OrderStatus::PAYMENT_FAILED, 'created_at' => now()->subHour()]);
        $abandoned->paymentAttempts()->forceCreate(['checkout_request_id' => 'ws_CO_OLD', 'phone' => '254700000000', 'amount' => 5000,
            'status' => PaymentAttempt::FAILED, 'created_at' => now()->subHour()]);
        $retrying = $this->order(['status' => OrderStatus::PAYMENT_FAILED, 'created_at' => now()->subHour()]);
        $retrying->paymentAttempts()->create(['checkout_request_id' => 'ws_CO_NEW', 'phone' => '254700000000', 'amount' => 5000,
            'status' => PaymentAttempt::FAILED]);
        $mismatch = $this->order(['status' => OrderStatus::AMOUNT_MISMATCH, 'created_at' => now()->subHour()]);

        $this->assertSame(3, StudioWorkflow::apply(CustomOrder::query(), 'review')->count());
        $this->artisan('stock:release-expired')->assertSuccessful();

        $this->assertSame(OrderStatus::EXPIRED, $abandoned->fresh()->status, 'nothing was charged; the checkout was abandoned');
        $this->assertSame(OrderStatus::PAYMENT_FAILED, $retrying->fresh()->status, 'a prompt inside the window may still be retried');
        $this->assertSame(OrderStatus::AMOUNT_MISMATCH, $mismatch->fresh()->status, 'collected money always waits for the studio');
        $this->assertSame(2, StudioWorkflow::apply(CustomOrder::query(), 'review')->count());
    }

    public function test_new_orders_figure_leaves_out_abandoned_and_cancelled_orders(): void
    {
        $this->order();
        $this->order(['status' => OrderStatus::PAID, 'is_paid' => true]);
        $this->order(['status' => OrderStatus::EXPIRED]);
        $this->order(['status' => OrderStatus::CANCELLED, 'payment_method' => 'whatsapp']);

        $this->actingAs($this->admin())->get(route('studio.index'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.new_orders_week', 2));
    }

    public function test_owner_is_alerted_to_a_new_custom_brief_with_a_studio_link(): void
    {
        Mail::fake();
        ThemeSetting::set('notification_email', 'owner@example.com');

        $this->postJson(route('custom-orders.store'), [
            'full_name' => 'Wanjiku', 'whatsapp_number' => '0712 345 678', 'notes' => 'Emerald two-piece for a wedding', 'priority_pass' => true,
        ])->assertOk();

        $order = CustomOrder::sole();
        Mail::assertQueued(NewCustomOrderAlertMail::class, function (NewCustomOrderAlertMail $mail) use ($order) {
            $html = $mail->render();

            return $mail->hasTo('owner@example.com')
                && str_contains($mail->envelope()->subject, 'Priority')
                && str_contains($html, 'Emerald two-piece')
                && str_contains($html, e(route('studio.index', ['tab' => 'orders', 'order' => $order->id])))
                && str_contains($html, 'https://wa.me/254712345678');
        });
    }

    public function test_studio_product_edits_cannot_overwrite_the_sold_count(): void
    {
        $product = $this->product(limit: 5, sold: 3);
        $category = Category::create(['name' => 'Sets', 'slug' => 'sets']);

        $this->actingAs($this->admin())->put(route('admin.catalog.update_product', $product), [
            'category_id' => $category->id, 'name' => $product->name, 'description' => 'Updated', 'price' => 5000,
            'batch_limit' => 5, 'batch_sold' => 0,
        ])->assertRedirect();

        $this->assertSame(3, $product->fresh()->batch_sold);
        $this->assertSame('Updated', $product->fresh()->description);
    }
}
