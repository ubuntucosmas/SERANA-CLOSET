<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CustomOrder;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\OrderStatus;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase E-e/f/g (design §15.5): the orders desk at the till. Click &
 * collect checks the customer's phone and that nothing is owed (the till can
 * take the balance first); the Studio can't mark collected around that.
 * Bespoke intake makes the same order the website does, with a quote and a
 * deposit at the till. A sale written on paper during an outage is entered
 * later, manager-gated, with its slip number and original time.
 */
class ShopDeskTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\StockFixtures;

    private User $cashier;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $tablet = User::factory()->create(['role' => Role::CASHIER, 'name' => 'Shop tablet']);
        $this->cashier = User::factory()->create(['role' => Role::CASHIER, 'name' => 'Wanjiku', 'pos_pin' => Hash::make('2468')]);
        $this->manager = User::factory()->create(['role' => Role::MANAGER, 'name' => 'Akinyi', 'pos_pin' => Hash::make('9753')]);
        $this->actingAs($tablet)->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $this->postJson(route('pos.till.open'), ['register' => 'Till 1', 'float' => 3000])->assertOk();
    }

    /** An online shop order, paid in part (a short M-Pesa payment). */
    private function onlineOrder(int $paid = 3000): CustomOrder
    {
        $product = $this->stockedProduct(3, ['name' => 'Linen Set', 'price' => 5000]);
        $order = CustomOrder::create(['full_name' => 'Jane Wanjiru', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'standard',
            'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000,
            'items_json' => [['id' => $product->id, 'name' => 'Linen Set', 'price' => 5000, 'quantity' => 1, 'options' => ['top_size' => 'One size']]]]);
        $this->holdFor($order, $product, 1, 'held', now()->addDay());
        app(OrderSettlementService::class)->recordPayment($order, $paid, 'mpesa_stk', Actor::safaricom(), 'QONLINE' . $paid);

        return $order->fresh();
    }

    public function test_collect_finds_the_order_by_reference_and_phone_only(): void
    {
        $order = $this->onlineOrder();

        $this->getJson(route('pos.collect.find', ['ref' => $order->cipher, 'phone' => '0799999999']))->assertNotFound();
        $found = $this->getJson(route('pos.collect.find', ['ref' => $order->cipher, 'phone' => '0712 345 678']))->assertOk()->json('order');
        $this->assertSame([$order->id, 2000, false], [$found['id'], $found['balance_due'], $found['can_collect']]);
    }

    public function test_the_balance_is_taken_at_the_till_then_the_order_is_collected(): void
    {
        $order = $this->onlineOrder();

        $this->postJson(route('pos.collect.store', $order), ['phone' => '0712345678'])->assertUnprocessable()
            ->assertJsonPath('errors.balance.0', 'KSh 2,000 is still owed. Take it first.');
        $this->postJson(route('pos.sales.tender', $order), ['method' => 'cash', 'amount' => 2000, 'tendered' => 2000])->assertOk()->assertJsonPath('sale.paid', true);
        $this->postJson(route('pos.collect.store', $order), ['phone' => '0700000000'])->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'That phone number isn’t the one on this order.');
        $this->postJson(route('pos.collect.store', $order), ['phone' => '0712345678', 'note' => 'Collected by her sister, showed SMS'])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame(['collected', 'collected', 'paid'], [$fresh->fulfilment_status, $fresh->status, $fresh->payment_status]);
        $this->assertSame([2000], CashMovement::where('type', 'sale')->pluck('amount')->all());
        $log = AuditLog::where('action', 'pos.collected')->sole();
        $this->assertSame([$this->cashier->id, 'Collected by her sister, showed SMS'], [$log->actor_id, $log->reason]);
        $this->postJson(route('pos.collect.store', $order), ['phone' => '0712345678'])->assertUnprocessable()->assertJsonPath('errors.order.0', 'This order was already collected.');
    }

    public function test_the_studio_cant_mark_collected_around_the_till(): void
    {
        $order = $this->onlineOrder(5000);
        $owner = User::factory()->create(['role' => Role::OWNER]);

        $this->actingAs($owner)->put(route('studio.update', $order), ['status' => 'collected', 'internal_notes' => null])
            ->assertSessionHasErrors(['status' => 'Mark it collected at the till, after checking the customer’s phone.']);
        $this->assertSame('pending', $order->fresh()->fulfilment_status);
    }

    public function test_bespoke_intake_makes_the_website_order_with_a_quote_and_takes_the_deposit(): void
    {
        Storage::fake(config('filesystems.public_disk'));
        $res = $this->post(route('pos.intake'), [
            'full_name' => 'Amina Otieno', 'phone' => '0712345678', 'email' => 'amina@example.com',
            'garment' => 'Gown', 'notes' => 'Emerald, floor length', 'bust_cm' => 92, 'waist_cm' => 74, 'hips_cm' => 100,
            'quote' => 12000, 'images' => [UploadedFile::fake()->image('reference.jpg')],
        ], ['Accept' => 'application/json'])->assertOk();

        $order = CustomOrder::findOrFail($res->json('order.id'));
        $this->assertSame(['bespoke', 'Gown', 12000, 6000, 'Amina Otieno', '254712345678', 92.0], [$order->kind, $order->type, $order->chargeable_total,
            $order->depositRequired(), $order->full_name, $order->whatsapp_number, (float) $order->bust_cm]);
        $this->assertCount(1, $order->inspiration_image_paths);
        $this->assertSame('Emerald, floor length', $order->items_json['notes']);
        $this->assertSame($this->cashier->id, AuditLog::where('action', 'pos.intake')->sole()->actor_id);

        $this->postJson(route('pos.sales.tender', $order), ['method' => 'cash', 'amount' => 6000, 'tendered' => 6000])->assertOk();
        $this->assertSame(['deposit_paid', 6000, 'pending'], [$order->fresh()->payment_status, $order->fresh()->amount_paid, $order->fresh()->fulfilment_status]);
    }

    private function slip(\App\Models\Product $product, string $number): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('pos.late_entry'), ['slip_number' => $number, 'occurred_at' => now()->subHours(2)->toIso8601String(),
            'reason' => 'Power cut', 'approval_pin' => '9753', 'items' => [['id' => $product->id, 'quantity' => 1, 'options' => ['top_size' => 'One size']]],
            'payments' => [['method' => 'cash', 'amount' => (int) $product->price]]]);
    }

    private function onHand(\App\Models\Product $product): ?int
    {
        return \App\Support\StockMode::v2() ? (int) \App\Models\StockLevel::where('product_variant_id', $this->oneSize($product)->id)->value('on_hand') : null;
    }

    public function test_a_slip_for_a_piece_since_sold_online_is_recorded_and_the_online_order_needs_a_decision(): void
    {
        $tee = $this->stockedProduct(1, ['name' => 'Linen Tee', 'price' => 2000]);
        $online = CustomOrder::create(['full_name' => 'Jane Wanjiru', 'email' => 'jane@example.com', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'standard', 'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 2000,
            'items_json' => [['id' => $tee->id, 'name' => 'Linen Tee', 'price' => 2000, 'quantity' => 1, 'options' => ['top_size' => 'One size']]]]);
        $this->holdFor($online, $tee, 1, 'held', now()->addDay());
        app(OrderSettlementService::class)->recordPayment($online, 2000, 'mpesa_stk', Actor::safaricom(), 'QSOLDONLINE');
        $this->assertSame([0, 'paid'], [$tee->fresh()->available_stock, $online->fresh()->payment_status]);

        // The last tee physically left the shop on a paper slip, during the outage.
        $sale = CustomOrder::findOrFail($this->slip($tee, 'B-0150')->assertOk()->json('sale.id'));

        $this->assertSame(['paid', 'collected', 'B-0150'], [$sale->payment_status, $sale->fulfilment_status, $sale->slip_number]);
        $fresh = $online->fresh();
        $this->assertSame(['needs_resolution', 'stock_gone', 'needs_resolution', 2000], [$fresh->resolution, $fresh->resolution_code, $fresh->payment_status, $fresh->amount_paid]);
        $this->assertStringContainsString('slip B-0150', $fresh->resolution_reason);
        $this->assertSame(0, $tee->fresh()->available_stock, 'one tee, sold once on the books');
        $this->assertNotSame(-1, $this->onHand($tee));
        if (\App\Support\StockMode::v2()) {
            $this->assertSame(0, $this->onHand($tee));
            $this->assertSame([['sale', -1], ['return', 1], ['sale', -1]], \App\Models\StockMovement::whereIn('type', ['sale', 'return'])->orderBy('id')->get()
                ->map(fn ($m) => [$m->type, $m->quantity])->all(), 'the online sale is taken back for the slip, and both are in the history');
        }
        Mail::assertQueued(\App\Mail\StockGoneMail::class, fn ($m) => $m->order->is($online) && str_contains((string) $m->why, 'sold in our shop'));
        $this->assertSame([$online->id], AuditLog::where('action', 'stock.taken_for_slip')->pluck('subject_id')->all());
    }

    public function test_a_slip_takes_an_unpaid_online_hold_and_a_later_payment_needs_a_decision(): void
    {
        $tee = $this->stockedProduct(1, ['name' => 'Linen Tee', 'price' => 2000]);
        $online = CustomOrder::create(['full_name' => 'Jane Wanjiru', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'standard',
            'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 2000,
            'items_json' => [['id' => $tee->id, 'name' => 'Linen Tee', 'price' => 2000, 'quantity' => 1, 'options' => ['top_size' => 'One size']]]]);
        $hold = $this->holdFor($online, $tee, 1, 'held', now()->addMinutes(10));

        $this->slip($tee, 'B-0151')->assertOk();

        $this->assertSame('released', $hold->fresh()->status, 'no money yet: the hold gives way');
        app(OrderSettlementService::class)->recordPayment($online, 2000, 'mpesa_stk', Actor::safaricom(), 'QLATEPAY');
        $this->assertSame(['needs_resolution', 'stock_gone'], [$online->fresh()->resolution, $online->fresh()->resolution_code], 'Phase C: paid after the stock went');
        $this->assertSame(0, $tee->fresh()->available_stock);
    }

    public function test_a_slip_beyond_the_records_is_recorded_and_flagged_for_a_count_never_below_zero(): void
    {
        $tee = $this->stockedProduct(0, ['name' => 'Linen Tee', 'price' => 2000, 'batch_sold' => 3]);

        $this->slip($tee, 'B-0152')->assertOk()->assertJsonPath('sale.paid', true);

        $log = AuditLog::where('action', 'stock.slip_shortfall')->sole();
        $this->assertStringContainsString('B-0152', $log->reason);
        $this->assertSame(0, $tee->fresh()->available_stock);
        if (\App\Support\StockMode::v2()) {
            $this->assertSame(0, $this->onHand($tee));
            $this->assertSame([['count_correction', 1], ['sale', -1]], \App\Models\StockMovement::where('type', '!=', 'count_correction')->orWhere('reason', 'like', '%B-0152%')
                ->orderBy('id')->get()->map(fn ($m) => [$m->type, $m->quantity])->all());
        } else {
            $this->assertSame([4, 4], [$tee->fresh()->batch_limit, $tee->fresh()->batch_sold]);
        }
    }

    public function test_a_paper_sale_is_entered_later_with_its_slip_and_time_and_a_manager(): void
    {
        $tee = $this->stockedProduct(4, ['name' => 'Linen Tee', 'price' => 2000]);
        $payload = ['slip_number' => 'B-0142', 'occurred_at' => now()->subHours(3)->toIso8601String(), 'reason' => 'Power cut, internet down',
            'items' => [['id' => $tee->id, 'quantity' => 2, 'options' => ['top_size' => 'One size']]],
            'payments' => [['method' => 'cash', 'amount' => 1500], ['method' => 'mpesa_manual', 'amount' => 2500, 'reference' => 'QOUTAGE1']]];

        $this->postJson(route('pos.late_entry'), $payload)->assertUnprocessable()->assertJsonPath('errors.approval_pin.0', 'A late entry needs a manager’s PIN.');
        $this->postJson(route('pos.late_entry'), ['payments' => [['method' => 'cash', 'amount' => 100]]] + $payload + ['approval_pin' => '9753'])
            ->assertUnprocessable()->assertJsonPath('errors.payments.0', 'The payments (KSh 100) must add up to the sale (KSh 4,000).');
        $res = $this->postJson(route('pos.late_entry'), $payload + ['approval_pin' => '9753'])->assertOk();

        $order = CustomOrder::findOrFail($res->json('sale.id'));
        $this->assertSame(['paid', 'collected', 2], [$order->payment_status, $order->fulfilment_status, $tee->fresh()->available_stock]);
        $this->assertTrue($order->created_at->diffInMinutes(now()->subHours(3)) < 2, 'dated when it happened');
        $this->assertSame([true], $order->payments()->get()->map(fn ($p) => $p->occurred_at->diffInMinutes(now()->subHours(3)) < 2)->unique()->values()->all());
        $this->assertSame([1500], CashMovement::where('type', 'sale')->pluck('amount')->all());
        $log = AuditLog::where('action', 'pos.late_entry')->sole();
        $this->assertSame(['B-0142', $this->manager->id, 'Power cut, internet down'], [$log->after['slip'], $log->after['approved_by'], $log->reason]);
        $this->postJson(route('pos.late_entry'), $payload + ['approval_pin' => '9753'])->assertUnprocessable()->assertJsonPath('errors.slip_number.0', 'Slip B-0142 was already entered.');
    }
}
