<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\TillSession;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The frontend audit (docs/audit/FRONTEND-AUDIT-2026-10-21.md): what the
 * screens need from the server so nobody gets stuck. A sale lost to a
 * reload is found again; its pieces can be changed; the owner sees past
 * drawers, their variances and where each order was sold.
 */
class FrontendAlignTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\StockFixtures;

    private User $tablet;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();
        $this->tablet = User::factory()->create(['role' => Role::CASHIER, 'name' => 'Shop tablet']);
        User::factory()->create(['role' => Role::CASHIER, 'name' => 'Wanjiku', 'pos_pin' => Hash::make('2468')]);
        User::factory()->create(['role' => Role::MANAGER, 'name' => 'Akinyi', 'pos_pin' => Hash::make('9753')]);
        $this->owner = User::factory()->create(['role' => Role::OWNER]);
        $this->actingAs($this->tablet)->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $this->postJson(route('pos.till.open'), ['register' => 'Till 1', 'float' => 1000])->assertOk();
    }

    private function line($product, int $qty = 1): array
    {
        return ['id' => $product->id, 'quantity' => $qty, 'options' => ['top_size' => \App\Models\ProductVariant::ONE_SIZE]];
    }

    public function test_todays_sales_on_this_till_can_be_found_again_after_a_reload(): void
    {
        $tee = $this->stockedProduct(5, ['name' => 'Linen Tee', 'price' => 2000]);
        $paid = $this->postJson(route('pos.sales.store'), ['items' => [$this->line($tee)]])->json('sale.id');
        $this->postJson(route('pos.sales.tender', $paid), ['method' => 'cash', 'amount' => 2000, 'tendered' => 2000]);
        $open = $this->postJson(route('pos.sales.store'), ['items' => [$this->line($tee, 2)]])->json('sale.id');

        $sales = $this->getJson(route('pos.sales.index'))->assertOk()->json('sales');

        $this->assertSame([$open, $paid], array_column($sales, 'id'), 'newest first');
        $this->assertSame([[4000, 4000, false], [2000, 0, true]], array_map(fn ($s) => [$s['total'], $s['balance_due'], $s['paid']], $sales));
        $this->assertSame('Wanjiku', $sales[0]['seller']);
        // The open sale reopens with what it needs to carry on, or to change its pieces.
        $sale = $this->getJson(route('pos.sales.show', $open))->json('sale');
        $this->assertEquals([['id' => $tee->id, 'name' => 'Linen Tee', 'price' => 2000, 'quantity' => 2, 'options' => ['top_size' => 'One size']]],
            array_map(fn ($i) => array_intersect_key($i, array_flip(['id', 'name', 'price', 'quantity', 'options'])), $sale['items']));
    }

    public function test_with_nothing_typed_the_till_shows_the_newest_pieces_first(): void
    {
        $older = $this->stockedProduct(2, ['name' => 'Alpha Coat']);
        $newer = $this->stockedProduct(2, ['name' => 'Zulu Tee']);

        $this->assertSame([$newer->id, $older->id], array_column($this->getJson(route('pos.catalogue'))->json('products'), 'id'));
        $this->assertSame([$older->id], array_column($this->getJson(route('pos.catalogue', ['q' => 'alpha']))->json('products'), 'id'));
    }

    public function test_a_walk_in_who_gives_an_email_gets_the_receipt_by_email(): void
    {
        $tee = $this->stockedProduct(5, ['name' => 'Linen Tee', 'price' => 2000]);
        $id = $this->postJson(route('pos.sales.store'), ['items' => [$this->line($tee)], 'customer' => ['email' => 'amina@example.com']])->json('sale.id');
        $this->postJson(route('pos.sales.tender', $id), ['method' => 'cash', 'amount' => 2000, 'tendered' => 2000])->assertOk();

        $this->assertSame('amina@example.com', CustomOrder::find($id)->email);
    }

    public function test_the_owner_sees_past_drawers_with_their_variance_and_z_report(): void
    {
        $this->postJson(route('pos.till.close'), ['counted' => 600, 'variance_reason' => 'Gave wrong change', 'approval_pin' => '9753'])->assertOk();

        $tills = $this->actingAs($this->owner)->get(route('studio.index', ['tab' => 'tills']))->viewData('page')['props']['tills'];

        $session = $tills['sessions'][0];
        $this->assertSame(['Till 1', 'Wanjiku', 'Wanjiku', 1000, 600, -400, 'Gave wrong change', 'Akinyi', true],
            [$session['register'], $session['opened_by'], $session['closed_by'], $session['report']['expected_cash'], $session['report']['counted_cash'],
                $session['report']['variance'], $session['report']['variance_reason'], $session['acknowledged_by'], $session['over_alert']]);
        $this->assertSame(200, $tills['variance_alert']);

        // The Overview flags it, so the owner sees it without looking.
        $attention = $this->get(route('studio.index'))->viewData('page')['props']['attention'];
        $this->assertSame([['register' => 'Till 1', 'variance' => -400]], array_map(fn ($v) => array_intersect_key($v, array_flip(['register', 'variance'])), $attention['till_variances']));
    }

    public function test_till_history_is_for_owners_and_managers(): void
    {
        $manager = User::factory()->create(['role' => Role::MANAGER]);
        $this->assertNotNull($this->actingAs($manager)->get(route('studio.index', ['tab' => 'tills']))->viewData('page')['props']['tills']);
        $this->assertContains('till.review', Role::abilities(Role::MANAGER));
        $this->assertNotContains('till.review', Role::abilities(Role::CASHIER));
    }

    public function test_the_studio_shows_where_each_order_was_sold_and_filters_the_till(): void
    {
        $tee = $this->stockedProduct(5, ['name' => 'Linen Tee', 'price' => 2000]);
        $sale = $this->postJson(route('pos.sales.store'), ['items' => [$this->line($tee)]])->json('sale.id');
        $online = CustomOrder::create(['full_name' => 'Jane', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'status' => 'Pending Payment', 'price_quoted' => 2000,
            'items_json' => [['id' => $tee->id, 'name' => 'Linen Tee', 'price' => 2000, 'quantity' => 1]]]);

        $orders = $this->actingAs($this->owner)->get(route('studio.index', ['tab' => 'orders', 'o_filter' => 'till']))->viewData('page')['props']['orders'];
        $this->assertSame([$sale], array_column($orders['data'], 'id'));
        $this->assertSame(['counter', 'Wanjiku', 'till'], [$orders['data'][0]['channel'], $orders['data'][0]['seller_name'], $orders['filter']]);

        // A sale still at the counter isn't Studio work: it stays off the Overview's waiting list.
        $awaiting = $this->get(route('studio.index'))->viewData('page')['props']['attention']['awaiting'];
        $this->assertSame([$online->id], collect($awaiting)->pluck('id')->all());
    }
}
