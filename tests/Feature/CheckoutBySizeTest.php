<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\StockService;
use App\Support\Actor;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D1 step 4 (design §13.3, §13.5.7, §13.6): with STOCK_V2 on, a
 * checkout holds each size it buys at the shop, under the stock level
 * locks. The last unit of one size is sold once while other sizes stay on
 * sale; a set holds its top and bottom; a bag line the server can't read as
 * a size (an old bag, a removed size, a tampered id) is refused per line,
 * never guessed.
 */
class CheckoutBySizeTest extends TestCase
{
    use RefreshDatabase;

    private Actor $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['serana.stock_v2' => 'on']);
        $this->owner = Actor::user(User::factory()->create(['role' => Role::OWNER]));
    }

    private function product(string $type = 'hoodie', array $stock = [], array $o = []): Product
    {
        $p = Product::create(array_merge(['name' => $type === 'set' ? 'Linen Set' : 'Linen Hoodie', 'slug' => "linen-{$type}-" . uniqid(), 'price' => 5000,
            'garment_type' => $type, 'batch_limit' => 10, 'in_stock' => true], $o));
        foreach ($stock as $key => $n) {
            [$part, $size] = str_contains($key, ':') ? explode(':', $key) : ['single', $key];
            app(StockService::class)->receive($p->variants()->where('part', $part)->where('size', $size)->sole(), Location::sole(), $n, $this->owner, 'Batch');
        }

        return $p;
    }

    private function checkout(array $items)
    {
        return $this->postJson(route('checkout.process'), ['full_name' => 'Buyer', 'email' => 'b@example.com', 'address' => '1 Street',
            'city' => 'Nairobi', 'phone' => '0712345678', 'items' => $items]);
    }

    private function holds(CustomOrder $order): array
    {
        return $order->stockReservations()->with('variant')->orderBy('id')->get()
            ->map(fn ($h) => [$h->variant->part . ':' . $h->variant->size, $h->quantity, $h->location_id, $h->second_part])->all();
    }

    public function test_buying_a_size_holds_that_size_and_leaves_the_others_on_sale(): void
    {
        $hoodie = $this->product('hoodie', ['M' => 1, 'L' => 2]);

        $this->checkout([['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'M']]])->assertOk();

        $order = CustomOrder::sole();
        $this->assertSame([['single:M', 1, Location::sole()->id, false]], $this->holds($order));
        $stock = app(StockService::class);
        $this->assertSame([0, 2], [$stock->availableOnline($hoodie->variants()->where('size', 'M')->sole()), $stock->availableOnline($hoodie->variants()->where('size', 'L')->sole())]);
        $this->assertSame(2, $hoodie->fresh()->available_stock);
        $this->assertSame(0, (int) $hoodie->fresh()->batch_sold, 'a hold sells nothing yet');
    }

    public function test_the_last_unit_of_a_size_is_sold_once(): void
    {
        $hoodie = $this->product('hoodie', ['M' => 1, 'L' => 1]);
        $this->checkout([['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'M']]])->assertOk();

        $this->flushSession(); // another shopper (the same browser would replace its own unpaid order)
        $this->checkout([['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'M']]])
            ->assertUnprocessable()->assertJsonPath('message', 'Linen Hoodie in Size M just sold out. Please review your bag and try again.');
        $this->checkout([['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'L']]])->assertOk();

        $this->assertSame(2, CustomOrder::count());
    }

    public function test_two_lines_of_one_size_count_together(): void
    {
        $hoodie = $this->product('hoodie', ['M' => 2]);

        $this->checkout([
            ['id' => $hoodie->id, 'quantity' => 2, 'options' => ['top_size' => 'M']],
            ['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'M', 'note' => 'gift']],
        ])->assertUnprocessable()->assertJsonPath('message', 'Linen Hoodie in Size M: only 2 left. Please review your bag and try again.');
        $this->assertSame(0, StockReservation::count());
    }

    public function test_a_set_holds_its_top_and_its_bottom(): void
    {
        $set = $this->product('set', ['top:M' => 1, 'bottom:L' => 1]);

        $this->checkout([['id' => $set->id, 'quantity' => 1, 'options' => ['top_size' => 'M', 'bottom_size' => 'L']]])->assertOk();

        $this->assertSame([['top:M', 1, Location::sole()->id, false], ['bottom:L', 1, Location::sole()->id, true]], $this->holds(CustomOrder::sole()));
        $this->assertSame(0, $set->fresh()->available_stock);
    }

    public function test_a_set_whose_bottom_is_gone_holds_nothing(): void
    {
        $set = $this->product('set', ['top:M' => 1]);

        $this->checkout([['id' => $set->id, 'quantity' => 1, 'options' => ['top_size' => 'M', 'bottom_size' => 'L']]])
            ->assertUnprocessable()->assertJsonPath('message', 'Linen Set in Bottom L just sold out. Please review your bag and try again.');
        $this->assertSame([0, 0], [CustomOrder::count(), StockReservation::count()]);
    }

    public function test_an_old_bag_line_without_a_size_asks_for_one(): void
    {
        $hoodie = $this->product('hoodie', ['M' => 3]);
        $cap = $this->product('hoodie', [], ['name' => 'Cap', 'specifications' => ['sizes' => ['One size']], 'batch_limit' => 5]);
        app(StockService::class)->receive($cap->variants()->sole(), Location::sole(), 5, $this->owner, 'Batch');

        $this->checkout([
            ['id' => $cap->id, 'quantity' => 1],
            ['id' => $hoodie->id, 'quantity' => 1],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Choose a size for Linen Hoodie.')
            ->assertJsonPath('line_errors.0', ['index' => 1, 'product_id' => $hoodie->id, 'message' => 'Choose a size for Linen Hoodie.']);
        $this->assertSame(0, CustomOrder::count());

        $this->checkout([['id' => $cap->id, 'quantity' => 1]])->assertOk(); // one size needs no choice
    }

    public function test_a_removed_size_or_a_tampered_variant_is_refused(): void
    {
        $hoodie = $this->product('hoodie', ['M' => 3, 'L' => 3]);
        $hoodie->variants()->where('size', 'L')->update(['active' => false]);
        $m = $hoodie->variants()->where('size', 'M')->value('id');

        $this->checkout([['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'L']]])
            ->assertUnprocessable()->assertJsonPath('message', 'Choose a size for Linen Hoodie.');
        $this->checkout([['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'L'], 'variant_ids' => [$m]]])
            ->assertUnprocessable();
        $this->checkout([['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'M'], 'variant_ids' => [$m]]])->assertOk();
        $this->assertSame([$m], CustomOrder::sole()->items_json[0]['variant_ids']);
    }

    public function test_made_to_order_pieces_are_never_held(): void
    {
        $free = $this->product('hoodie', [], ['batch_limit' => null]);

        $this->checkout([['id' => $free->id, 'quantity' => 4, 'options' => ['top_size' => 'XL']]])->assertOk();

        $this->assertSame(0, StockReservation::count());
    }

    public function test_with_v2_off_checkout_holds_by_product_as_before(): void
    {
        config(['serana.stock_v2' => 'off']);
        $hoodie = $this->product('hoodie', [], ['batch_limit' => 2]);

        $this->checkout([['id' => $hoodie->id, 'quantity' => 2, 'options' => ['top_size' => 'M']]])->assertOk();

        $hold = StockReservation::sole();
        $this->assertSame([$hoodie->id, null, 2], [$hold->product_id, $hold->product_variant_id, $hold->quantity]);
    }
}
