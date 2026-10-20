<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CustomOrder;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Role;
use App\Support\StockMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase E-d (design §15.4): returns and exchanges at the till. A return
 * line has a reason and a disposition (restock, or write off); the returned
 * value becomes owed back by the Phase C rules and is refunded from the
 * drawer, by an M-Pesa code, or later from the Studio. An exchange turns it
 * into credit on a new sale. A cashier needs a manager's PIN; nothing ever
 * refunds more than was paid or returns more than was sold.
 */
class ReturnsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\StockFixtures; // the same returns in either stock model (Phase D1)

    private User $cashier;
    private User $manager;
    private Product $jacket;
    private Product $tee;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $tablet = User::factory()->create(['role' => Role::CASHIER, 'name' => 'Shop tablet']);
        $this->cashier = User::factory()->create(['role' => Role::CASHIER, 'name' => 'Wanjiku', 'pos_pin' => Hash::make('2468')]);
        $this->manager = User::factory()->create(['role' => Role::MANAGER, 'name' => 'Akinyi', 'pos_pin' => Hash::make('9753')]);
        $this->jacket = $this->stockedProduct(5, ['name' => 'Wool Jacket', 'price' => 5000]);
        $this->tee = $this->stockedProduct(5, ['name' => 'Linen Tee', 'price' => 2000]);
        $this->actingAs($tablet)->postJson(route('pos.unlock'), ['pin' => '2468'])->assertOk();
        $this->postJson(route('pos.till.open'), ['register' => 'Till 1', 'float' => 10000])->assertOk();
    }

    private function line(Product $p, int $qty = 1): array
    {
        return ['id' => $p->id, 'quantity' => $qty, 'options' => ['top_size' => \App\Models\ProductVariant::ONE_SIZE]];
    }

    /** A paid counter sale: two jackets in cash. */
    private function paidSale(array $items = null, string $method = 'cash'): CustomOrder
    {
        $id = $this->postJson(route('pos.sales.store'), ['items' => $items ?? [$this->line($this->jacket, 2)]])->assertOk()->json('sale.id');
        $total = CustomOrder::find($id)->chargeable_total;
        $this->postJson(route('pos.sales.tender', $id), $method === 'cash' ? ['method' => 'cash', 'amount' => $total, 'tendered' => $total]
            : ['method' => 'mpesa_manual', 'amount' => $total, 'reference' => 'QSALE' . $id])->assertJsonPath('sale.paid', true);

        return CustomOrder::find($id);
    }

    private function itemLine(CustomOrder $order, Product $p): OrderLine
    {
        return $order->lines()->where('line_type', OrderLine::ITEM)->where('product_id', $p->id)->sole();
    }

    private function returnLines(CustomOrder $order, array $lines, array $extra = [])
    {
        return $this->postJson(route('pos.returns.store'), array_merge(['order_id' => $order->id, 'lines' => $lines, 'reason' => 'Wrong fit'], $extra));
    }

    public function test_the_till_finds_a_sale_and_what_can_still_be_returned(): void
    {
        $order = $this->paidSale();

        $found = $this->getJson(route('pos.orders.find', ['q' => $order->cipher]))->assertOk()->json('order');
        $this->assertSame([$order->id, 10000, 10000], [$found['id'], $found['total'], $found['paid']]);
        $this->assertEquals([['description' => 'Wool Jacket', 'quantity' => 2, 'returnable' => 2, 'unit' => 5000]],
            array_map(fn ($l) => array_intersect_key($l, array_flip(['description', 'quantity', 'returnable', 'unit'])), $found['lines']));
        $this->getJson(route('pos.orders.find', ['q' => 'SRN-NOPE']))->assertNotFound();
    }

    public function test_a_cashier_returns_with_a_manager_pin_and_refunds_cash_from_the_drawer(): void
    {
        $order = $this->paidSale();
        $line = $this->itemLine($order, $this->jacket);

        $this->returnLines($order, [['order_line_id' => $line->id, 'quantity' => 1, 'disposition' => 'restock']], ['refund_method' => 'cash'])
            ->assertUnprocessable()->assertJsonPath('errors.approval_pin.0', 'A return needs a manager’s PIN.');
        $this->returnLines($order, [['order_line_id' => $line->id, 'quantity' => 1, 'disposition' => 'restock']], ['refund_method' => 'cash', 'approval_pin' => '9753'])
            ->assertOk()->assertJsonPath('refunded', 5000);

        $fresh = $order->fresh();
        $this->assertSame([5000, 5000, 'paid'], [$fresh->chargeable_total, $fresh->amount_paid, $fresh->payment_status]);
        $adjust = $fresh->lines()->where('line_type', OrderLine::ADJUSTMENT)->sole();
        $this->assertSame([-5000, $line->id, 'restock', $this->manager->id, 'Wrong fit'], [$adjust->amount, $adjust->meta['return_of'], $adjust->meta['disposition'], $adjust->meta['approved_by'], $adjust->reason]);
        $refund = $fresh->payments()->where('type', OrderPayment::REFUND)->sole();
        $this->assertSame(['cash', 5000], [$refund->method, $refund->amount]);
        $this->assertSame([-5000, $refund->id], [CashMovement::where('type', 'refund')->sole()->amount, CashMovement::where('type', 'refund')->sole()->order_payment_id]);
        $this->assertSame(4, $this->jacket->fresh()->available_stock, 'one jacket back on sale');
        $this->assertSame($this->manager->id, AuditLog::where('action', 'pos.return')->sole()->after['approved_by']);
        if (StockMode::v2()) {
            $this->assertSame([1], StockMovement::where('type', 'return')->pluck('quantity')->all());
        }
    }

    public function test_a_manager_needs_no_second_pin(): void
    {
        $order = $this->paidSale();
        $this->postJson(route('pos.unlock'), ['pin' => '9753']);

        $this->returnLines($order, [['order_line_id' => $this->itemLine($order, $this->jacket)->id, 'quantity' => 1, 'disposition' => 'restock']], ['refund_method' => 'cash'])->assertOk();
    }

    public function test_a_written_off_return_never_goes_back_on_sale(): void
    {
        $order = $this->paidSale();

        $this->returnLines($order, [['order_line_id' => $this->itemLine($order, $this->jacket)->id, 'quantity' => 1, 'disposition' => 'write_off']],
            ['refund_method' => 'mpesa_manual', 'refund_reference' => 'QREFUND1', 'approval_pin' => '9753'])->assertOk();

        $this->assertSame(3, $this->jacket->fresh()->available_stock, 'the damaged jacket stays off sale');
        $this->assertSame(['mpesa_manual', 'QREFUND1'], [$order->payments()->where('type', 'refund')->value('method'), $order->payments()->where('type', 'refund')->value('external_reference')]);
        $this->assertSame(0, CashMovement::where('type', 'refund')->count(), 'not from the drawer');
        if (StockMode::v2()) {
            $this->assertSame([['return', 1], ['damage', -1]], StockMovement::whereIn('type', ['return', 'damage'])->orderBy('id')->get()->map(fn ($m) => [$m->type, $m->quantity])->all());
        }
    }

    public function test_nothing_is_returned_twice_or_refunded_beyond_what_was_paid(): void
    {
        $order = $this->paidSale();
        $line = $this->itemLine($order, $this->jacket);
        $this->returnLines($order, [['order_line_id' => $line->id, 'quantity' => 2, 'disposition' => 'restock']], ['refund_method' => 'cash', 'approval_pin' => '9753'])->assertOk();

        $this->returnLines($order, [['order_line_id' => $line->id, 'quantity' => 1, 'disposition' => 'restock']], ['refund_method' => 'cash', 'approval_pin' => '9753'])
            ->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Only 0 of Wool Jacket can still be returned.');
        $this->assertSame([0, 0, 10000], [$order->fresh()->chargeable_total, $order->fresh()->amount_paid, (int) $order->payments()->where('type', 'refund')->sum('amount')]);
    }

    public function test_the_drawer_must_hold_the_cash_refund(): void
    {
        $order = $this->paidSale(null, 'mpesa');
        $this->postJson(route('pos.till.payout'), ['amount' => 9000, 'reason' => 'Bank', 'type' => 'pickup', 'approval_pin' => '9753'])->assertOk();

        $this->returnLines($order, [['order_line_id' => $this->itemLine($order, $this->jacket)->id, 'quantity' => 1, 'disposition' => 'restock']],
            ['refund_method' => 'cash', 'approval_pin' => '9753'])->assertUnprocessable()->assertJsonPath('errors.refund_method.0', 'Only KSh 1,000 is in the drawer. Refund by M-Pesa, or later from the Studio.');
        $this->assertSame(10000, $order->fresh()->chargeable_total, 'nothing changed');
    }

    public function test_a_return_can_be_refunded_later_from_the_studio(): void
    {
        $order = $this->paidSale();

        $this->returnLines($order, [['order_line_id' => $this->itemLine($order, $this->jacket)->id, 'quantity' => 1, 'disposition' => 'restock']], ['refund_method' => 'later', 'approval_pin' => '9753'])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame(['refund_due', -5000, 'Returned at the till'], [$fresh->payment_status, $fresh->balance_due, $fresh->refund_cause]);
    }

    public function test_an_exchange_turns_the_return_into_credit_on_a_new_sale(): void
    {
        $order = $this->paidSale(); // two jackets, 10,000
        $line = $this->itemLine($order, $this->jacket);
        $this->returnLines($order, [['order_line_id' => $line->id, 'quantity' => 1, 'disposition' => 'restock']], ['refund_method' => 'credit', 'approval_pin' => '9753'])->assertOk();

        // The new sale: three tees (6,000). 5,000 credit, 1,000 cash.
        $new = $this->postJson(route('pos.sales.store'), ['items' => [$this->line($this->tee, 3)]])->json('sale.id');
        $this->postJson(route('pos.sales.tender', $new), ['method' => 'credit', 'amount' => 6000, 'credit_order_id' => $order->id])
            ->assertOk()->assertJsonPath('booked', 5000)->assertJsonPath('sale.balance_due', 1000);
        $this->postJson(route('pos.sales.tender', $new), ['method' => 'cash', 'amount' => 1000, 'tendered' => 1000])->assertJsonPath('sale.paid', true);

        $old = $order->fresh();
        $this->assertSame([5000, 5000, 'paid'], [$old->chargeable_total, $old->amount_paid, $old->payment_status]);
        $this->assertSame(['credit', 5000], [$old->payments()->where('type', 'refund')->value('method'), (int) $old->payments()->where('type', 'refund')->value('amount')]);
        $this->assertSame([['credit', 5000], ['cash', 1000]], CustomOrder::find($new)->payments()->orderBy('id')->get()->map(fn ($p) => [$p->method, $p->amount])->all());
        $this->assertSame(0, CashMovement::where('type', 'refund')->count(), 'credit never leaves the drawer');
    }

    public function test_credit_is_limited_to_what_the_old_sale_owes_back(): void
    {
        $order = $this->paidSale();
        $new = $this->postJson(route('pos.sales.store'), ['items' => [$this->line($this->tee)]])->json('sale.id');

        $this->postJson(route('pos.sales.tender', $new), ['method' => 'credit', 'amount' => 2000, 'credit_order_id' => $order->id])
            ->assertUnprocessable()->assertJsonPath('errors.credit_order_id.0', 'Nothing is owed back on ' . $order->cipher . ' to use as credit.');
    }
}
