<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\OrderStatus;
use App\Support\Role;
use App\Support\StudioWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase C follow-up: a cancelled custom order keeps the production stage it
 * reached ("Cancelled at: sewing"); the cancelled order state is what takes
 * it out of every Studio queue and count, in either read mode.
 */
class CancelledOrderQueuesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->create(['role' => Role::OWNER]);
    }

    /** One cancelled order at every stage a custom order can be cancelled at. */
    private function cancelledAtEveryStage(): array
    {
        $orders = [];
        foreach (['pending', 'designing', 'sewing', 'completed'] as $stage) {
            $order = CustomOrder::create(['full_name' => "Cancelled {$stage}", 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => $stage, 'price_quoted' => 10000]);
            app(OrderSettlementService::class)->recordPayment($order, $stage === 'completed' ? 10000 : 5000, 'cash', Actor::user($this->owner), "Cash {$stage}");
            $this->actingAs($this->owner)->post(route('studio.order.cancel_bespoke', $order), ['reason' => 'Client cancelled'])->assertSessionHasNoErrors();
            $orders[$stage] = $order->fresh();
        }

        return $orders;
    }

    public function test_a_cancelled_order_keeps_its_stage_and_reads_as_cancelled(): void
    {
        foreach ($this->cancelledAtEveryStage() as $stage => $order) {
            $this->assertSame([$stage, 'cancelled', OrderStatus::CANCELLED], [$order->fulfilment_status, $order->order_state, $order->status]);
            $this->assertSame(OrderStatus::CANCELLED, $order->legacyStatus(true));
            $this->assertSame(OrderStatus::CANCELLED, $order->publicTrackingPayload()['status']);
        }
    }

    /** @dataProvider modes */
    public function test_cancelled_orders_are_in_no_studio_queue_or_count(bool $ledgerReads): void
    {
        config(['serana.ledger_reads' => $ledgerReads]);
        $this->cancelledAtEveryStage();

        foreach (StudioWorkflow::QUEUES as $queue) {
            $this->assertSame(0, StudioWorkflow::apply(CustomOrder::query(), $queue)->count(), "queue {$queue}");
        }
        $props = $this->actingAs($this->owner)->get(route('studio.index'))->viewData('page')['props'];
        $this->assertSame(0, array_sum($props['workflow']), 'Home queue counts');
        $this->assertSame([0, 0], [$props['stats']['new_orders_week'], $props['stats']['in_production']]);
        $this->assertSame([], collect($props['attention']['awaiting'])->pluck('id')->all(), 'Home attention snapshot');
        foreach (['designing', 'sewing', 'completed'] as $stage) {
            $this->assertSame(0, CustomOrder::withLegacyStatus([$stage])->count(), "stage list {$stage}");
        }
        $this->assertSame(4, CustomOrder::withLegacyStatus([OrderStatus::CANCELLED])->count());
    }

    public static function modes(): array
    {
        return ['legacy reads' => [false], 'ledger reads' => [true]];
    }

    public function test_the_studio_shows_the_stage_it_was_cancelled_at_and_will_not_move_it(): void
    {
        $sewing = $this->cancelledAtEveryStage()['sewing'];

        $focus = $this->actingAs($this->owner)->get(route('studio.index', ['tab' => 'orders', 'order' => $sewing->id]))->viewData('page')['props']['focus_order'];
        $this->assertSame(['cancelled', 'sewing'], [$focus['order_state'], $focus['fulfilment_status']]);

        $this->put(route('studio.update', $sewing), ['status' => 'completed', 'internal_notes' => null])
            ->assertSessionHasErrors(['status' => 'This order is cancelled. Its stage stays where it was cancelled.']);
        $this->assertSame('sewing', $sewing->fresh()->fulfilment_status);
    }
}
