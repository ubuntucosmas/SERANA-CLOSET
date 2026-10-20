<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\TillSession;
use App\Models\User;
use App\Services\StockService;
use App\Support\Actor;
use App\Support\Role;
use App\Support\ShopPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase E-c (design §15.3): a sale at the counter. Stock is held at the
 * till's shop; a discount over the cashier's limit needs a manager's PIN;
 * tenders split across cash (change is never booked), an M-Pesa code or a
 * prompt to the customer's phone; every till payment knows its session and
 * cash lands in the drawer. Paid in full, the stock is sold and the sale is
 * collected. An unpaid sale can be voided.
 */
class CounterSaleTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\StockFixtures; // the same counter in either stock model (Phase D1)

    private User $tablet;
    private User $cashier;
    private User $manager;
    private Product $jacket;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mpesa.callback_token' => 'secret', 'services.mpesa.query_spacing_seconds' => 0]);
        $this->tablet = User::factory()->create(['role' => Role::CASHIER, 'name' => 'Shop tablet']);
        $this->cashier = User::factory()->create(['role' => Role::CASHIER, 'name' => 'Wanjiku', 'pos_pin' => Hash::make('2468')]);
        $this->manager = User::factory()->create(['role' => Role::MANAGER, 'name' => 'Akinyi', 'pos_pin' => Hash::make('9753')]);
        $this->jacket = $this->stockedProduct(3, ['name' => 'Wool Jacket', 'price' => 5000]);
        $this->actingAs($this->tablet)->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $this->postJson(route('pos.till.open'), ['register' => 'Till 1', 'float' => 2000])->assertOk();
    }

    private function line(Product $p, int $qty = 1, array $o = []): array
    {
        return array_merge(['id' => $p->id, 'quantity' => $qty, 'options' => ['top_size' => \App\Models\ProductVariant::ONE_SIZE]], $o);
    }

    private function sale(array $items, array $extra = [])
    {
        return $this->postJson(route('pos.sales.store'), array_merge(['items' => $items], $extra));
    }

    private function tender(int $order, string $method, int $amount, array $extra = [])
    {
        return $this->postJson(route('pos.sales.tender', $order), array_merge(['method' => $method, 'amount' => $amount], $extra));
    }

    public function test_the_catalogue_finds_by_name_sku_or_barcode_with_stock_at_this_shop(): void
    {
        $variant = $this->oneSize($this->jacket);

        foreach (['wool', $variant->sku, $variant->barcode] as $q) {
            $found = collect($this->getJson(route('pos.catalogue', ['q' => $q]))->assertOk()->json('products'))->firstWhere('id', $this->jacket->id);
            $this->assertNotNull($found, $q);
            $this->assertEquals([['id' => $variant->id, 'part' => 'single', 'size' => 'One size', 'available' => 3, 'sku' => $variant->sku]],
                array_map(fn ($s) => array_intersect_key($s, array_flip(['id', 'part', 'size', 'available', 'sku'])), $found['sizes']));
        }
        $this->assertSame($this->jacket->id, $this->getJson(route('pos.catalogue', ['q' => $variant->barcode]))->json('exact.product_id'), 'a scanned barcode picks the size');
    }

    public function test_a_sale_holds_its_stock_at_the_shop_as_the_pin_holder(): void
    {
        $res = $this->sale([$this->line($this->jacket, 2)], ['customer' => ['name' => 'Jane', 'phone' => '0712345678']])->assertOk();

        $order = CustomOrder::findOrFail($res->json('sale.id'));
        $this->assertSame(['shop', 'counter', 10000, 10000, TillSession::sole()->id, 'Jane', '254712345678'],
            [$order->kind, $order->channel, $order->chargeable_total, $order->balance_due, $order->till_session_id, $order->full_name, $order->phone]);
        $hold = StockReservation::where('custom_order_id', $order->id)->sole();
        $this->assertSame(['held', 2], [$hold->status, $hold->quantity]);
        $this->assertTrue($hold->expires_at->between(now()->addMinutes(ShopPolicy::posHoldMinutes() - 1), now()->addMinutes(ShopPolicy::posHoldMinutes() + 1)));
        $this->assertSame(1, $this->jacket->fresh()->available_stock);
        $this->assertSame($this->cashier->id, AuditLog::where('action', 'pos.sale_started')->sole()->actor_id);

        $this->sale([$this->line($this->jacket, 2)])->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'Wool Jacket'));
    }

    public function test_a_walk_in_needs_no_customer_details(): void
    {
        $order = CustomOrder::findOrFail($this->sale([$this->line($this->jacket)])->assertOk()->json('sale.id'));
        $this->assertSame('Walk-in customer', $order->full_name);
    }

    public function test_a_discount_within_the_cashier_limit_needs_no_manager_and_over_it_does(): void
    {
        $two = [$this->line($this->jacket, 2)]; // 10,000: the limit is min(10%, 500) = 500

        $this->sale($two, ['discount' => 600, 'discount_reason' => 'Loyal customer'])->assertUnprocessable()
            ->assertJsonPath('errors.approval_pin.0', 'A discount over KSh 500 needs a manager’s PIN.');
        $this->sale($two, ['discount' => 600, 'discount_reason' => 'Loyal customer', 'approval_pin' => '2468'])->assertUnprocessable();
        $this->sale($two, ['discount' => 400])->assertUnprocessable()->assertJsonValidationErrors('discount_reason');

        $ok = $this->sale($two, ['discount' => 600, 'discount_reason' => 'Loyal customer', 'approval_pin' => '9753'])->assertOk();
        $order = CustomOrder::findOrFail($ok->json('sale.id'));
        $line = $order->lines()->where('line_type', OrderLine::DISCOUNT)->sole();
        $this->assertSame([-600, $this->cashier->id, 'Loyal customer', $this->manager->id], [$line->amount, $line->actor_id, $line->reason, $line->meta['approved_by']]);
        $this->assertSame(9400, $order->chargeable_total);
    }

    public function test_split_tenders_cash_with_change_then_an_mpesa_code_complete_the_sale(): void
    {
        $id = $this->sale([$this->line($this->jacket)])->json('sale.id');

        $cash = $this->tender($id, 'cash', 3000, ['tendered' => 5000])->assertOk();
        $this->assertSame([3000, 2000, 2000, false], [$cash->json('booked'), $cash->json('change'), $cash->json('sale.balance_due'), $cash->json('sale.paid')]);
        $this->tender($id, 'mpesa_manual', 2000, ['reference' => 'QPOS12345'])->assertOk()->assertJsonPath('sale.paid', true);

        $order = CustomOrder::findOrFail($id);
        $session = TillSession::sole();
        $this->assertSame(['paid', 'collected', 'collected'], [$order->payment_status, $order->fulfilment_status, $order->status]);
        $this->assertSame([[3000, 'cash', $session->id], [2000, 'mpesa_manual', $session->id]],
            $order->payments()->orderBy('id')->get()->map(fn ($p) => [$p->amount, $p->method, $p->till_session_id])->all());
        $this->assertSame([['sale', 3000, 5000]], CashMovement::where('type', 'sale')->get()->map(fn ($m) => [$m->type, $m->amount, $m->tendered])->all());
        $this->assertSame(2000 + 3000, app(\App\Services\TillService::class)->expectedCash($session), 'the change never lands in the drawer');
        $this->assertSame(1, (int) $this->jacket->fresh()->batch_sold);
        $this->assertSame('committed', StockReservation::where('custom_order_id', $id)->value('status'));
    }

    public function test_cash_never_books_more_than_is_owed(): void
    {
        $id = $this->sale([$this->line($this->jacket)])->json('sale.id');

        $res = $this->tender($id, 'cash', 5000, ['tendered' => 20000])->assertOk();

        $this->assertSame([5000, 15000, true], [$res->json('booked'), $res->json('change'), $res->json('sale.paid')]);
        $this->tender($id, 'cash', 100, ['tendered' => 100])->assertUnprocessable()->assertJsonPath('errors.amount.0', 'Nothing more is owed on this sale.');
        $this->tender($id, 'cash', 100, ['tendered' => 50])->assertUnprocessable();
    }

    public function test_an_mpesa_prompt_goes_to_the_customers_phone_and_the_callback_books_it_to_the_till(): void
    {
        $id = $this->sale([$this->line($this->jacket)])->json('sale.id');
        $this->tender($id, 'cash', 1000, ['tendered' => 1000]);
        Http::fake(['*oauth/v1/generate*' => Http::response(['access_token' => 'fake']), '*stkpush/v1/processrequest*' => Http::response(['CheckoutRequestID' => 'ws_POS_1'])]);

        $this->tender($id, 'mpesa_prompt', 4000, ['phone' => '0712345678'])->assertOk()->assertJsonPath('prompt.status', 'sent');
        $this->assertSame(4000, CustomOrder::findOrFail($id)->paymentAttempts()->sole()->amount);

        $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => ['ResultCode' => 0, 'CheckoutRequestID' => 'ws_POS_1',
            'CallbackMetadata' => ['Item' => [['Name' => 'Amount', 'Value' => 4000], ['Name' => 'MpesaReceiptNumber', 'Value' => 'QPOSSTK1']]]]]])->assertOk();

        $order = CustomOrder::findOrFail($id);
        $this->assertSame(['paid', 'collected'], [$order->payment_status, $order->fulfilment_status]);
        $this->assertSame(TillSession::sole()->id, OrderPayment::where('external_reference', 'QPOSSTK1')->value('till_session_id'));
        $this->getJson(route('pos.sales.show', $id))->assertJsonPath('sale.paid', true);
    }

    public function test_an_unpaid_sale_is_voided_by_its_cashier_and_others_need_a_manager(): void
    {
        $mine = $this->sale([$this->line($this->jacket)])->json('sale.id');
        $this->postJson(route('pos.sales.void', $mine), ['reason' => 'Customer changed mind'])->assertOk();
        $this->assertSame(['cancelled', 'released'], [CustomOrder::find($mine)->order_state, StockReservation::where('custom_order_id', $mine)->value('status')]);
        $this->assertSame(3, $this->jacket->fresh()->available_stock);

        $theirs = $this->sale([$this->line($this->jacket)])->json('sale.id');
        $other = User::factory()->create(['role' => Role::CASHIER, 'pos_pin' => Hash::make('1357')]);
        $this->postJson(route('pos.unlock'), ['pin' => '1357'])->assertOk();
        $this->postJson(route('pos.sales.void', $theirs), ['reason' => 'x'])->assertUnprocessable()->assertJsonPath('errors.approval_pin.0', 'Voiding someone else’s sale needs a manager’s PIN.');
        $this->postJson(route('pos.sales.void', $theirs), ['reason' => 'Abandoned at the counter', 'approval_pin' => '9753'])->assertOk();

        $paid = $this->sale([$this->line($this->jacket)])->json('sale.id');
        $this->tender($paid, 'cash', 1000, ['tendered' => 1000]);
        $this->postJson(route('pos.sales.void', $paid), ['reason' => 'x', 'approval_pin' => '9753'])->assertUnprocessable()
            ->assertJsonPath('errors.reason.0', 'Money was taken on this sale: refund it instead of voiding.');
    }

    public function test_the_receipt_shows_lines_tenders_change_and_the_footer(): void
    {
        ShopPolicy::save(['receipt_footer' => 'Exchanges within 7 days with this receipt.']);
        $id = $this->sale([$this->line($this->jacket)], ['discount' => 300, 'discount_reason' => 'Display piece'])->json('sale.id');
        $this->tender($id, 'cash', 4700, ['tendered' => 5000]);

        $html = $this->get(route('pos.sales.receipt', $id))->assertOk()->getContent();
        foreach (['Wool Jacket', 'KSh 5,000', 'Discount', '-KSh 300', 'KSh 4,700', 'Cash', 'Tendered KSh 5,000', 'Change KSh 300', 'Served by Wanjiku', 'Exchanges within 7 days with this receipt.', CustomOrder::find($id)->cipher] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
    }

    public function test_the_z_report_matches_the_ledger(): void
    {
        $a = $this->sale([$this->line($this->jacket)])->json('sale.id');
        $this->tender($a, 'cash', 5000, ['tendered' => 5000]);
        $b = $this->sale([$this->line($this->jacket)], ['discount' => 200, 'discount_reason' => 'Small mark'])->json('sale.id');
        $this->tender($b, 'mpesa_manual', 4800, ['reference' => 'QZREP1']);
        $c = $this->sale([$this->line($this->jacket)])->json('sale.id');
        $this->postJson(route('pos.sales.void', $c), ['reason' => 'Walked off']);

        $report = $this->getJson(route('pos.till.report'))->json('report');
        $this->assertSame(['cash' => 5000, 'mpesa_manual' => 4800], $report['sales_by_method']);
        $this->assertSame([2, 1, 200, 7000], [$report['sales_count'], $report['voids'], $report['discounts'], $report['expected_cash']]);
        $this->assertSame((int) OrderPayment::where('till_session_id', TillSession::sole()->id)->sum('amount'), array_sum($report['sales_by_method']));
    }

    public function test_only_staff_with_the_till_permission_sell(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::CLIENT]))->postJson(route('pos.sales.store'), ['items' => [$this->line($this->jacket)]])->assertForbidden();
    }

    public function test_a_sale_needs_an_open_till(): void
    {
        $this->postJson(route('pos.till.close'), ['counted' => 2000])->assertOk();
        $this->sale([$this->line($this->jacket)])->assertUnprocessable()->assertJsonPath('errors.till.0', 'Open Till 1 first.');
    }

    public function test_with_stock_per_size_the_counter_sells_sizes_from_this_shop_only(): void
    {
        config(['serana.stock_v2' => 'on']);
        $hoodie = Product::create(['name' => 'Linen Hoodie', 'slug' => 'linen-hoodie', 'price' => 4000, 'garment_type' => 'hoodie', 'batch_limit' => 10, 'in_stock' => true, 'specifications' => ['sizes' => ['M', 'L']]]);
        $shop = Location::query()->online()->first();
        $popup = Location::create(['name' => 'Pop-up', 'fulfils_online' => false, 'accepts_pickup' => false, 'sort' => 5]);
        $owner = Actor::user($this->manager);
        app(StockService::class)->receive($hoodie->variants()->where('size', 'M')->sole(), $shop, 1, $owner, 'Batch');
        app(StockService::class)->receive($hoodie->variants()->where('size', 'L')->sole(), $popup, 5, $owner, 'Batch'); // L only at the pop-up

        $this->sale([['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'L']]])->assertUnprocessable();
        $id = $this->sale([['id' => $hoodie->id, 'quantity' => 1, 'options' => ['top_size' => 'M']]])->assertOk()->json('sale.id');
        $this->tender($id, 'cash', 4000, ['tendered' => 4000])->assertJsonPath('sale.paid', true);

        $sale = StockMovement::where('custom_order_id', $id)->sole();
        $this->assertSame([-1, $shop->id], [$sale->quantity, $sale->location_id]);
    }
}
