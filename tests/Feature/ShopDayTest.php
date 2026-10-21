<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\OrderStatus;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase E end to end (design §15): one day at the shop through the till's
 * own endpoints — open, sell (change, split tenders, a discount, a void),
 * hand over a part-paid online order, take a bespoke deposit, a return with
 * a cash refund, a payout, close — and the drawer, the Z report, the ledger
 * check and the stock reconciliation all agree at the end.
 */
class ShopDayTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\StockFixtures;

    private function line($product, int $qty = 1): array
    {
        return ['id' => $product->id, 'quantity' => $qty, 'options' => ['top_size' => \App\Models\ProductVariant::ONE_SIZE]];
    }

    private function sale(array $items, array $extra = []): int
    {
        return $this->postJson(route('pos.sales.store'), ['items' => $items] + $extra)->assertOk()->json('sale.id');
    }

    private function tender(int $id, array $body)
    {
        return $this->postJson(route('pos.sales.tender', $id), $body)->assertOk();
    }

    public function test_a_day_at_the_shop_balances_to_the_shilling(): void
    {
        Mail::fake();
        $this->withoutVite();
        $tablet = User::factory()->create(['role' => Role::CASHIER, 'name' => 'Shop tablet']);
        User::factory()->create(['role' => Role::CASHIER, 'name' => 'Wanjiku', 'pos_pin' => Hash::make('2468')]);
        User::factory()->create(['role' => Role::MANAGER, 'name' => 'Akinyi', 'pos_pin' => Hash::make('9753')]);
        $jacket = $this->stockedProduct(5, ['name' => 'Wool Jacket', 'price' => 5000]);
        $tee = $this->stockedProduct(5, ['name' => 'Linen Tee', 'price' => 2000]);
        $set = $this->stockedProduct(3, ['name' => 'Linen Set', 'price' => 5000]);
        // Last night's online order, part paid: collected today.
        $online = CustomOrder::create(['full_name' => 'Jane Wanjiru', 'phone' => '254712345678', 'whatsapp_number' => '254712345678', 'type' => 'standard',
            'status' => OrderStatus::PENDING_PAYMENT, 'price_quoted' => 5000, 'items_json' => [['id' => $set->id, 'name' => 'Linen Set', 'price' => 5000, 'quantity' => 1,
                'options' => ['top_size' => \App\Models\ProductVariant::ONE_SIZE]]]]);
        $this->holdFor($online, $set, 1, 'held', now()->addDay());
        app(OrderSettlementService::class)->recordPayment($online, 3000, 'mpesa_stk', Actor::safaricom(), 'QONLINE1');

        // Morning: Wanjiku unlocks and opens with 5,000.
        $this->actingAs($tablet)->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $this->get(route('pos.index'))->assertInertia(fn ($page) => $page->component('Pos/Till')->where('operator.name', 'Wanjiku')->where('tills', []));
        $this->postJson(route('pos.till.open'), ['register' => 'Till 1', 'float' => 5000])->assertOk();

        // Two tees, paid with a 5,000 note.
        $s1 = $this->sale([$this->line($tee, 2)]);
        $this->tender($s1, ['method' => 'cash', 'amount' => 4000, 'tendered' => 5000])->assertJsonPath('change', 1000)->assertJsonPath('sale.paid', true);
        // A jacket, split: M-Pesa then cash.
        $s2 = $this->sale([$this->line($jacket)]);
        $this->tender($s2, ['method' => 'mpesa_manual', 'amount' => 3000, 'reference' => 'QDAY0001']);
        $this->tender($s2, ['method' => 'cash', 'amount' => 2000, 'tendered' => 2000])->assertJsonPath('sale.paid', true);
        // A tee with the cashier's own discount.
        $s3 = $this->sale([$this->line($tee)], ['discount' => 200, 'discount_reason' => 'Regular customer']);
        $this->tender($s3, ['method' => 'cash', 'amount' => 1800, 'tendered' => 2000])->assertJsonPath('change', 200);
        // A sale the customer walked away from.
        $s4 = $this->sale([$this->line($jacket)]);
        $this->postJson(route('pos.sales.void', $s4), ['reason' => 'Changed her mind'])->assertOk();

        // Jane collects: the balance first, then the hand-over.
        $this->getJson(route('pos.collect.find', ['ref' => $online->cipher, 'phone' => '0712345678']))->assertJsonPath('order.balance_due', 2000);
        $this->tender($online->id, ['method' => 'cash', 'amount' => 2000, 'tendered' => 2000]);
        $this->postJson(route('pos.collect.store', $online), ['phone' => '0712345678'])->assertOk();

        // A bespoke gown, quoted at the counter; the deposit by M-Pesa.
        $gown = $this->postJson(route('pos.intake'), ['full_name' => 'Amina Otieno', 'phone' => '0722000111', 'garment' => 'Gown', 'quote' => 10000])->assertOk()->json('order');
        $this->assertSame(5000, $gown['deposit_required']);
        $this->tender($gown['id'], ['method' => 'mpesa_manual', 'amount' => 5000, 'reference' => 'QDAY0002']);

        // Afternoon: one tee from the first sale comes back; cash from the drawer with Akinyi's PIN.
        $teeLine = CustomOrder::find($s1)->lines()->where('line_type', OrderLine::ITEM)->sole();
        $this->postJson(route('pos.returns.store'), ['order_id' => $s1, 'reason' => 'Too small', 'refund_method' => 'cash', 'approval_pin' => '9753',
            'lines' => [['order_line_id' => $teeLine->id, 'quantity' => 1, 'disposition' => 'restock']]])->assertOk()->assertJsonPath('refunded', 2000);
        $this->postJson(route('pos.till.payout'), ['amount' => 300, 'reason' => 'Boda to the bank', 'approval_pin' => '9753'])->assertOk();

        // Close: 5,000 + 4,000 + 2,000 + 1,800 + 2,000 − 2,000 − 300 = 12,500 in the drawer.
        $this->getJson(route('pos.till.report'))->assertJsonPath('report.expected_cash', 12500);
        $report = $this->postJson(route('pos.till.close'), ['counted' => 12500])->assertOk()->json('report');

        $this->assertSame([12500, 0], [$report['expected_cash'], $report['variance']]);
        $this->assertEquals(['cash' => 9800, 'mpesa_manual' => 8000], $report['sales_by_method'], 'the online M-Pesa from last night is not today’s till');
        $this->assertEquals(['cash' => 2000], $report['refunds_by_method']);
        $this->assertSame([3, 1, 200], [$report['sales_count'], $report['voids'], $report['discounts']]);
        $this->assertSame(['collected', 'paid'], [$online->fresh()->fulfilment_status, $online->fresh()->payment_status]);
        $this->assertSame(['deposit_paid', 'pending'], [CustomOrder::find($gown['id'])->payment_status, CustomOrder::find($gown['id'])->fulfilment_status]);
        $this->assertSame([3, 4, 2], [$tee->fresh()->available_stock, $jacket->fresh()->available_stock, $set->fresh()->available_stock]);

        $this->artisan('serana:ledger-check')->expectsOutputToContain('0 mismatch(es)')->assertSuccessful();
        $this->artisan('serana:stock:reconcile')->expectsOutputToContain('Stock reconciliation passed.')->assertSuccessful();
    }

    public function test_the_till_page_loads_locked_for_staff_and_not_at_all_for_customers(): void
    {
        $this->withoutVite();
        $tablet = User::factory()->create(['role' => Role::CASHIER]);
        $this->actingAs($tablet)->get(route('pos.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Pos/Till')->where('operator', null)->has('policy.idle_minutes'));

        $customer = User::factory()->create(['role' => Role::CLIENT]);
        $this->actingAs($customer)->get(route('pos.index'))->assertForbidden();
    }

    public function test_a_cashier_signs_in_to_the_till(): void
    {
        User::factory()->create(['role' => Role::CASHIER, 'email' => 'till@serana.test', 'password' => Hash::make('a-strong-password-1')]);

        $this->post(route('login'), ['email' => 'till@serana.test', 'password' => 'a-strong-password-1'])->assertRedirect(route('pos.index'));
    }
}
