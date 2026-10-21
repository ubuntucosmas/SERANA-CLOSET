<?php

namespace Tests\Feature;

use App\Mail\StockGoneMail;
use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Services\StockService;
use App\Support\Actor;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase D1 step 5 (design §13.4): with STOCK_V2 on, paying sells sizes.
 * Committing a hold writes one sale movement at its location (and still
 * counts the piece in batch_sold, so switching back stays safe). The Phase C
 * rules work per size: a late payment takes a size only if it is still free,
 * a set waits as a whole, an M-Pesa retry re-takes a free size, a swap is
 * to a size with stock, and nothing is ever sold below zero.
 */
class SettlementBySizeTest extends TestCase
{
    use RefreshDatabase;

    private Actor $owner;
    private User $ownerUser;
    private Location $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['serana.stock_v2' => 'on', 'services.store.notification_email' => 'owner@serana.test']);
        $this->ownerUser = User::factory()->create(['role' => Role::OWNER]);
        $this->owner = Actor::user($this->ownerUser);
        $this->shop = Location::sole();
    }

    private function product(string $name, string $type = 'hoodie', int $price = 5000, array $stock = []): Product
    {
        $p = Product::create(['name' => $name, 'slug' => str($name)->slug() . '-' . uniqid(), 'price' => $price, 'garment_type' => $type, 'batch_limit' => 10, 'in_stock' => true]);
        foreach ($stock as $key => $n) {
            app(StockService::class)->receive($this->v($p, $key), $this->shop, $n, $this->owner, 'Batch');
        }

        return $p;
    }

    private function v(Product $p, string $key)
    {
        [$part, $size] = str_contains($key, ':') ? explode(':', $key) : ['single', $key];

        return $p->variants()->where('part', $part)->where('size', $size)->sole();
    }

    /** An order holding sizes ($holds: key => [qty, status]), as checkout would leave it. */
    private function order(Product $p, array $holds, array $options): CustomOrder
    {
        $units = max(array_map(fn ($h) => $h[0], $holds));
        $order = CustomOrder::create(['full_name' => 'Jane Wanjiru', 'email' => 'jane@example.com', 'whatsapp_number' => '254712345678', 'phone' => '254712345678',
            'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => (int) $p->price * $units,
            'items_json' => [['id' => $p->id, 'name' => $p->name, 'price' => (int) $p->price, 'quantity' => $units, 'options' => $options]]]);
        $first = true;
        foreach ($holds as $key => [$qty, $status]) {
            $order->stockReservations()->create(['product_id' => $p->id, 'product_variant_id' => $this->v($p, $key)->id, 'location_id' => $this->shop->id,
                'quantity' => $qty, 'second_part' => ! $first, 'status' => $status, 'expires_at' => $status === 'held' ? now()->addMinutes(10) : now()->subMinute()]);
            $first = false;
        }

        return $order;
    }

    private function pay(CustomOrder $order, string $code): array
    {
        return app(OrderSettlementService::class)->recordPayment($order->fresh(), null, 'mpesa_stk', Actor::safaricom(), $code);
    }

    private function onHand(Product $p, string $key): int
    {
        return (int) StockLevel::where('product_variant_id', $this->v($p, $key)->id)->where('location_id', $this->shop->id)->value('on_hand');
    }

    public function test_paying_sells_each_size_once_with_a_sale_movement(): void
    {
        $set = $this->product('Linen Set', 'set', 9000, ['top:M' => 2, 'bottom:L' => 2]);
        $order = $this->order($set, ['top:M' => [1, 'held'], 'bottom:L' => [1, 'held']], ['top_size' => 'M', 'bottom_size' => 'L']);

        $this->pay($order, 'QSET1');
        $this->pay($order, 'QSET1'); // a replayed callback

        $this->assertSame([1, 1], [$this->onHand($set, 'top:M'), $this->onHand($set, 'bottom:L')]);
        $sales = StockMovement::where('type', 'sale')->orderBy('id')->get();
        $this->assertSame([[-1, $order->id], [-1, $order->id]], $sales->map(fn ($m) => [$m->quantity, $m->custom_order_id])->all());
        $this->assertSame($order->stockReservations()->orderBy('id')->pluck('id')->all(), $sales->pluck('stock_reservation_id')->all());
        $this->assertSame(['Sold'], $sales->pluck('reason')->unique()->values()->all(), 'the order is its own column, not repeated in the reason');
        $this->assertSame(['committed'], $order->stockReservations()->pluck('status')->unique()->values()->all());
        $this->assertSame(1, $set->fresh()->batch_sold, 'one set, counted once for switching back');
        $this->assertSame([], app(StockService::class)->integrityIssues());
    }

    public function test_a_late_payment_takes_a_size_that_is_still_free(): void
    {
        $hoodie = $this->product('Linen Hoodie', 'hoodie', 5000, ['M' => 1]);
        $order = $this->order($hoodie, ['M' => [1, 'released']], ['top_size' => 'M']);

        $this->assertTrue($this->pay($order, 'QLATE1')['became_paid']);
        $this->assertSame(0, $this->onHand($hoodie, 'M'));
    }

    public function test_a_late_payment_for_a_size_sold_meanwhile_waits_and_names_the_size(): void
    {
        $hoodie = $this->product('Linen Hoodie', 'hoodie', 5000, ['M' => 1, 'L' => 3]);
        $late = $this->order($hoodie, ['M' => [1, 'released']], ['top_size' => 'M']);
        $other = $this->order($hoodie, ['M' => [1, 'held']], ['top_size' => 'M']);
        $this->pay($other, 'QOTHER');

        $result = $this->pay($late, 'QLATE2');

        $this->assertSame(['Linen Hoodie (Size M)'], $result['stock_gone']);
        $fresh = $late->fresh();
        $this->assertSame(['needs_resolution', 'stock_gone', 5000], [$fresh->payment_status, $fresh->resolution_code, $fresh->amount_paid]);
        $this->assertStringContainsString('Linen Hoodie (Size M) sold out', $fresh->resolution_reason);
        $this->assertSame([0, 3], [$this->onHand($hoodie, 'M'), $this->onHand($hoodie, 'L')], 'never below zero; L untouched');
        Mail::assertQueued(StockGoneMail::class, fn ($m) => $m->assertSeeInHtml('Linen Hoodie (Size M)') !== null);
    }

    public function test_a_set_whose_bottom_sold_waits_as_a_whole_and_keeps_its_top(): void
    {
        $set = $this->product('Linen Set', 'set', 9000, ['top:M' => 1, 'bottom:L' => 1]);
        $late = $this->order($set, ['top:M' => [1, 'released'], 'bottom:L' => [1, 'released']], ['top_size' => 'M', 'bottom_size' => 'L']);
        app(StockService::class)->damage($this->v($set, 'bottom:L'), $this->shop, 1, $this->owner, 'Stained');

        $result = $this->pay($late, 'QLATE3');

        $this->assertSame(['Linen Set (Bottom L)'], $result['stock_gone']);
        $holds = $late->stockReservations()->with('variant')->get()->mapWithKeys(fn ($h) => [$h->variant->part => $h->status]);
        $this->assertSame(['top' => 'held', 'bottom' => 'released'], $holds->sortKeys()->reverse()->all(), 'the free top is kept for the decision');
        $this->assertSame(0, StockMovement::where('type', 'sale')->count());
    }

    public function test_an_mpesa_retry_retakes_a_free_size_and_refuses_a_gone_one(): void
    {
        $hoodie = $this->product('Linen Hoodie', 'hoodie', 5000, ['M' => 1, 'L' => 1]);
        $free = $this->order($hoodie, ['L' => [1, 'released']], ['top_size' => 'L']);
        $this->assertTrue($free->secureStockForPayment());
        $this->assertSame('held', $free->stockReservations()->value('status'));

        $gone = $this->order($hoodie, ['M' => [1, 'released']], ['top_size' => 'M']);
        $this->pay($this->order($hoodie, ['M' => [1, 'held']], ['top_size' => 'M']), 'QBUY');
        $this->assertFalse($gone->secureStockForPayment());
        $this->assertSame('released', $gone->stockReservations()->value('status'), 'nothing changes when refused');
    }

    public function test_a_short_payment_holds_its_sizes_for_the_balance(): void
    {
        $hoodie = $this->product('Linen Hoodie', 'hoodie', 5000, ['M' => 1]);
        $order = $this->order($hoodie, ['M' => [1, 'held']], ['top_size' => 'M']);

        app(OrderSettlementService::class)->recordPayment($order, 4000, 'mpesa_stk', Actor::safaricom(), 'QSHORT');
        app(OrderSettlementService::class)->holdForBalance($order);

        $hold = $order->stockReservations()->sole();
        $this->assertTrue($hold->expires_at->between(now()->addHours(23), now()->addHours(25)));
        $this->assertSame(0, app(StockService::class)->availableOnline($this->v($hoodie, 'M')));
    }

    public function test_a_swap_is_to_a_size_with_stock(): void
    {
        $jacket = $this->product('Last Jacket', 'hoodie', 5000, ['M' => 1]);
        $coat = $this->product('Wool Coat', 'hoodie', 6000, ['L' => 1]);
        $late = $this->order($jacket, ['M' => [1, 'released']], ['top_size' => 'M']);
        $this->pay($this->order($jacket, ['M' => [1, 'held']], ['top_size' => 'M']), 'QFIRST');
        $this->pay($late, 'QLATE4');

        $resolve = fn (array $data) => $this->actingAs($this->ownerUser)->post(route('studio.order.resolve', $late), $data + ['action' => 'swap', 'from_product_id' => $jacket->id, 'to_product_id' => $coat->id, 'reason' => 'Chose the coat']);
        $resolve(['size' => 'M'])->assertSessionHasErrors(['to_product_id' => 'Wool Coat in Size M just sold out.']);
        $resolve(['size' => 'L'])->assertSessionHasNoErrors();

        $fresh = $late->fresh();
        $this->assertSame(['none', 1000], [$fresh->resolution, $fresh->balance_due]);
        $this->assertSame('Wool Coat', $fresh->items_json[0]['name']);
        $this->assertSame([$this->v($coat, 'L')->id], $fresh->items_json[0]['variant_ids']);
        $this->assertSame('held', $late->stockReservations()->where('product_variant_id', $this->v($coat, 'L')->id)->value('status'));

        app(OrderSettlementService::class)->recordPayment($fresh, 1000, 'mpesa_stk', Actor::safaricom(), 'QBAL');
        $this->assertSame(['paid', 0, 0], [$late->fresh()->payment_status, $this->onHand($coat, 'L'), $this->onHand($jacket, 'M')]);
        $this->assertSame([], app(StockService::class)->integrityIssues());
    }

    public function test_a_set_swap_takes_both_sizes(): void
    {
        $gone = $this->product('Gone Hoodie', 'hoodie', 9000, ['M' => 1]);
        $set = $this->product('Linen Set', 'set', 9000, ['top:S' => 1, 'bottom:M' => 1]);
        $late = $this->order($gone, ['M' => [1, 'released']], ['top_size' => 'M']);
        app(StockService::class)->damage($this->v($gone, 'M'), $this->shop, 1, $this->owner, 'Lost');
        $this->pay($late, 'QLATE5');

        $this->actingAs($this->ownerUser)->post(route('studio.order.resolve', $late), ['action' => 'swap', 'from_product_id' => $gone->id, 'to_product_id' => $set->id,
            'top_size' => 'S', 'bottom_size' => 'M', 'reason' => 'Same price set'])->assertSessionHasNoErrors();

        $this->assertSame(['paid', 0, 0], [$late->fresh()->payment_status, $this->onHand($set, 'top:S'), $this->onHand($set, 'bottom:M')]);
        $this->assertSame('Top S · Bottom M', $late->fresh()->items_json[0]['size_label']);
    }

    public function test_a_full_refund_releases_the_sizes_without_moving_stock(): void
    {
        $set = $this->product('Linen Set', 'set', 9000, ['top:M' => 1]);
        $late = $this->order($set, ['top:M' => [1, 'released'], 'bottom:L' => [1, 'released']], ['top_size' => 'M', 'bottom_size' => 'L']);
        $this->pay($late, 'QLATE6');

        $this->actingAs($this->ownerUser)->post(route('studio.order.resolve', $late), ['action' => 'refund', 'reason' => 'Wants the money back'])->assertSessionHasNoErrors();

        $this->assertSame(['released'], $late->stockReservations()->pluck('status')->unique()->values()->all());
        $this->assertSame(1, app(StockService::class)->availableOnline($this->v($set, 'top:M')));
        $this->assertSame(0, StockMovement::where('type', 'sale')->count());
    }
}
