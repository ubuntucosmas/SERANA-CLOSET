<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockOperation;
use App\Models\User;
use App\Services\StockService;
use App\Support\Actor;
use App\Support\StudioStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockEntryTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        config(['serana.stock_v2' => 'on']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->product = Product::create(['name' => 'Studio Hoodie', 'slug' => 'studio-hoodie', 'price' => 5000, 'garment_type' => 'hoodie', 'batch_limit' => 10, 'in_stock' => true, 'specifications' => ['sizes' => ['M', 'L']]]);
    }

    private function payload(string $action = 'receive', array $quantities = [3, 2]): array
    {
        return ['operation_id' => (string) Str::uuid(), 'action' => $action, 'reason' => 'Workshop delivery', 'reference' => 'DELIVERY-45', 'exclude_paid_pickups' => true,
            'rows' => $this->product->variants()->orderBy('id')->get()->values()->map(fn ($v, $i) => ['variant_id' => $v->id, 'quantity' => $quantities[$i], 'expected_version' => StockLevel::where('product_variant_id', $v->id)->value('version') ?? 0])->all()];
    }

    private function submit(array $data)
    {
        return $this->actingAs($this->manager)->post(route('studio.stock.batch', $this->product), $data);
    }

    public function test_receiving_multiple_sizes_records_one_delivery_and_retry_does_not_add_again(): void
    {
        $data = $this->payload();
        $this->submit($data)->assertSessionHasNoErrors();
        $this->submit($data)->assertSessionHasNoErrors();
        $this->assertSame([3, 2], StockLevel::orderBy('product_variant_id')->pluck('on_hand')->all());
        $this->assertSame(2, StockMovement::count());
        $this->assertSame(1, StockOperation::count());
        $this->assertSame(['DELIVERY-45'], StockOperation::pluck('reference')->all());
        $this->assertSame([$data['operation_id']], StockMovement::pluck('stock_operation_id')->unique()->values()->all());
        $this->assertSame(5, $this->product->fresh()->batch_limit);
    }

    public function test_reusing_an_entry_number_for_different_quantities_is_rejected(): void
    {
        $data = $this->payload();
        $this->submit($data)->assertSessionHasNoErrors();
        $data['rows'][0]['quantity'] = 20;
        $this->submit($data)->assertSessionHasErrors('operation_id');
        $this->assertSame(5, (int) StockLevel::sum('on_hand'));
    }

    public function test_a_stale_count_after_a_sale_cannot_restore_the_sold_unit(): void
    {
        $this->submit($this->payload())->assertSessionHasNoErrors();
        $count = $this->payload('count', [3, 2]);
        $v = $this->product->variants()->orderBy('id')->first();
        app(StockService::class)->move($v, Location::sole(), StockMovement::SALE, -1, Actor::system(), 'Sold');
        $this->submit($count)->assertSessionHasErrors('rows.0.quantity');
        $this->assertSame([2, 2], StockLevel::orderBy('product_variant_id')->pluck('on_hand')->all());
        $this->assertSame(1, StockOperation::count(), 'the failed entry is rolled back');
    }

    public function test_change_then_reversal_still_invalidates_an_old_count(): void
    {
        $this->submit($this->payload())->assertSessionHasNoErrors();
        $count = $this->payload('count', [3, 2]);
        $v = $this->product->variants()->orderBy('id')->first();
        $stock = app(StockService::class);
        $stock->receive($v, Location::sole(), 1, Actor::user($this->manager), 'Extra');
        $stock->damage($v, Location::sole(), 1, Actor::user($this->manager), 'Damaged');
        $this->submit($count)->assertSessionHasErrors('rows.0.quantity');
    }

    public function test_a_batch_rolls_back_every_row_if_a_later_write_off_fails(): void
    {
        $this->submit($this->payload())->assertSessionHasNoErrors();
        $this->submit($this->payload('damage', [1, 10]))->assertSessionHasErrors('rows.1.quantity');
        $this->assertSame([3, 2], StockLevel::orderBy('product_variant_id')->pluck('on_hand')->all());
        $this->assertSame(2, StockMovement::count());
        $this->assertSame(1, StockOperation::count());
    }

    public function test_count_zero_is_saved_and_an_unchanged_count_also_invalidates_old_forms(): void
    {
        $data = $this->payload('count', [0, 0]);
        $this->submit($data)->assertSessionHasNoErrors();
        $this->submit($data)->assertSessionHasNoErrors(); // safe retry even after versions change
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(2, StockLevel::whereNotNull('counted_at')->count());
        $this->assertSame([1, 1], StockLevel::orderBy('product_variant_id')->pluck('version')->all());
        $data['operation_id'] = (string) Str::uuid();
        $this->submit($data)->assertSessionHasErrors('rows.0.quantity');
    }

    public function test_counts_require_both_a_version_and_pickup_exclusion_confirmation(): void
    {
        $data = $this->payload('count');
        unset($data['rows'][0]['expected_version']);
        $data['exclude_paid_pickups'] = false;
        $this->submit($data)->assertSessionHasErrors(['rows.0.expected_version', 'exclude_paid_pickups']);
        $this->assertSame(0, StockOperation::count());
    }

    public function test_foreign_inactive_duplicate_and_made_to_order_variants_are_refused(): void
    {
        $data = $this->payload();
        $data['rows'][1]['variant_id'] = $data['rows'][0]['variant_id'];
        $this->submit($data)->assertSessionHasErrors('rows.0.variant_id');
        $other = Product::create(['name' => 'Other', 'slug' => 'other', 'price' => 100, 'garment_type' => 'hoodie']);
        $data = $this->payload();
        $data['rows'][0]['variant_id'] = $other->variants()->first()->id;
        $this->submit($data)->assertSessionHasErrors('rows');
        $this->product->variants()->first()->update(['active' => false]);
        $this->submit($this->payload())->assertSessionHasErrors('rows');
        $this->product->variants()->update(['active' => true]);
        $this->product->update(['track_stock' => false]);
        $this->submit($this->payload())->assertSessionHasErrors('rows');
        $this->assertSame(0, StockOperation::count());
    }

    public function test_set_receipts_keep_top_and_bottom_sizes_separate(): void
    {
        $this->product = Product::create(['name' => 'Set', 'slug' => 'set', 'price' => 6000, 'garment_type' => 'set', 'batch_limit' => 10, 'specifications' => ['sizes' => ['M', 'L']]]);
        $this->submit($this->payload('receive', [2, 1, 4, 3]))->assertSessionHasNoErrors();
        $this->assertSame([['top', 'M', 2], ['top', 'L', 1], ['bottom', 'M', 4], ['bottom', 'L', 3]],
            $this->product->variants()->orderBy('id')->get()->map(fn ($v) => [$v->part, $v->size, (int) $v->levels()->value('on_hand')])->all());
        $this->assertSame(3, $this->product->fresh()->batch_limit, 'three complete sets, not ten unrelated components');
    }

    public function test_cashiers_cannot_enter_stock_and_single_size_routes_cannot_bypass_safeguards(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']))->post(route('studio.stock.batch', $this->product), $this->payload())->assertRedirect('/');
        $this->actingAs($this->manager)->post(route('studio.stock.move'), ['variant_id' => $this->product->variants()->first()->id, 'action' => 'count', 'quantity' => 5, 'reason' => 'Count'])->assertSessionHasErrors(['operation_id', 'exclude_paid_pickups', 'quantity']);
    }

    public function test_paid_pickup_units_are_shown_separately_and_do_not_increase_unsold_counts(): void
    {
        $this->submit($this->payload())->assertSessionHasNoErrors();
        $v = $this->product->variants()->orderBy('id')->first();
        $order = CustomOrder::create(['full_name' => 'Pickup buyer', 'whatsapp_number' => '254712345678', 'type' => 'standard', 'price_quoted' => 5000, 'fulfilment_method' => 'pickup', 'pickup_location_id' => Location::sole()->id, 'status' => 'ready_for_pickup']);
        app(StockService::class)->move($v, Location::sole(), StockMovement::SALE, -1, Actor::system(), 'Sold', ['custom_order_id' => $order->id]);
        $sizes = collect(StudioStock::forStudio(false)['products'])->firstWhere('id', $this->product->id)['sizes'];
        $this->assertSame([2, 1], [$sizes[0]['on_hand'], $sizes[0]['paid_awaiting_pickup']]);
        $this->submit($this->payload('count', [2, 2]))->assertSessionHasNoErrors();
        $this->assertSame(2, StockLevel::where('product_variant_id', $v->id)->value('on_hand'));
        $order->update(['status' => 'collected']);
        $sizes = collect(StudioStock::forStudio(false)['products'])->firstWhere('id', $this->product->id)['sizes'];
        $this->assertSame(0, $sizes[0]['paid_awaiting_pickup']);
        $this->assertSame(2, StockLevel::where('product_variant_id', $v->id)->value('on_hand'), 'handover does not deduct twice');
    }
}
