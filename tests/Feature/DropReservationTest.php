<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Drop;
use App\Models\User;
use App\Services\OrderSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DropReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.drops.enabled' => true]); // these tests cover the enabled flow
    }

    private function liveDrop(array $overrides = []): Drop
    {
        return Drop::create(array_merge([
            'title' => 'Midnight Trench',
            'price' => 8500,
            'target_quantity' => 3,
            'status' => 'live',
            'ends_at' => now()->addDays(2),
        ], $overrides));
    }

    public function test_reserving_a_live_drop_creates_a_pending_drop_order(): void
    {
        $drop = $this->liveDrop();

        $res = $this->postJson(route('drops.reserve', $drop), [
            'full_name' => 'Jane Doe',
            'phone' => '0712345678',
            'email' => 'jane@example.com',
            'size' => 'M',
            'bust_cm' => 86,
            'waist_cm' => 68,
            'hips_cm' => 92,
            'height_cm' => 168,
            'fit_notes' => 'Relaxed fit please',
        ]);

        $res->assertOk()->assertJson(['success' => true]);

        $order = CustomOrder::latest()->first();
        $this->assertSame('drop', $order->type);
        $this->assertSame($drop->id, $order->drop_id);
        $this->assertEquals(8500, (int) $order->price_quoted);
        $this->assertFalse((bool) $order->is_paid);
        // Per-reserver sizing is captured for production.
        $this->assertEquals(86, (int) $order->bust_cm);
        $this->assertSame('M', $order->items_json['size']);
        $this->assertEquals(168, (int) $order->items_json['height_cm']);
        $this->assertSame('Relaxed fit please', $order->items_json['fit_notes']);
    }

    public function test_a_drop_with_offered_sizes_requires_a_valid_size(): void
    {
        $drop = $this->liveDrop(['sizes' => ['S', 'M', 'L']]);

        // Missing size → rejected.
        $this->postJson(route('drops.reserve', $drop), ['full_name' => 'A', 'phone' => '0712345678'])
            ->assertStatus(422);
        // Size not in the offered set → rejected.
        $this->postJson(route('drops.reserve', $drop), ['full_name' => 'A', 'phone' => '0712345678', 'size' => 'XXL'])
            ->assertStatus(422);
        // Valid size → accepted.
        $this->postJson(route('drops.reserve', $drop), ['full_name' => 'A', 'phone' => '0712345678', 'size' => 'M'])
            ->assertOk();
    }

    public function test_a_closed_drop_rejects_reservations(): void
    {
        $drop = $this->liveDrop(['status' => 'closed']);

        $this->postJson(route('drops.reserve', $drop), [
            'full_name' => 'Jane Doe',
            'phone' => '0712345678',
        ])->assertStatus(422);

        $this->assertSame(0, CustomOrder::count());
    }

    public function test_admin_can_create_a_drop_with_an_image(): void
    {
        $admin = User::factory()->create(['role' => 'owner']);

        $res = $this->actingAs($admin)->post(route('admin.drops.store'), [
            'title' => 'Midnight Trench',
            'description' => 'Heavy cotton trench.',
            'price' => 8500,
            'target_quantity' => 25,
            'status' => 'live',
            'image' => UploadedFile::fake()->image('concept.jpg'),
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('drops', ['title' => 'Midnight Trench', 'status' => 'live']);
        // The uploaded file becomes image_path; the raw 'image' key is never a column.
        $this->assertNotNull(Drop::first()->image_path);
    }

    public function test_paid_reservations_count_toward_the_drop(): void
    {
        $drop = $this->liveDrop(['target_quantity' => 2]);

        $this->postJson(route('drops.reserve', $drop), ['full_name' => 'A', 'phone' => '0712345678']);
        $order = CustomOrder::latest()->first();

        // Unpaid: does not count.
        $this->assertSame(0, $drop->fresh()->reserved_count);

        // Paid via the shared settlement service: counts and drives funded %.
        app(OrderSettlementService::class)->markPaid($order, 'RCPT1', []);

        $fresh = $drop->fresh();
        $this->assertSame(1, $fresh->reserved_count);
        $this->assertSame(50, $fresh->funded_percent);
        $this->assertFalse($fresh->is_funded);
    }

    public function test_expired_drops_are_greenlit_or_closed_at_deadline(): void
    {
        // Funded + past deadline → greenlit. (Create the paid reservation
        // directly since an expired drop no longer accepts new reservations.)
        $funded = $this->liveDrop(['target_quantity' => 1, 'ends_at' => now()->subHour()]);
        CustomOrder::create([
            'drop_id' => $funded->id, 'type' => 'drop', 'status' => 'Paid', 'is_paid' => true,
            'price_quoted' => $funded->price, 'full_name' => 'A', 'phone' => '0712345678',
            'whatsapp_number' => '0712345678',
        ]);

        // Unfunded + past deadline → closed.
        $unfunded = $this->liveDrop(['target_quantity' => 5, 'ends_at' => now()->subHour()]);

        // Still open → untouched.
        $open = $this->liveDrop(['ends_at' => now()->addDay()]);

        $this->artisan('drops:close-expired')->assertSuccessful();

        $this->assertSame('greenlit', $funded->fresh()->status);
        $this->assertSame('closed', $unfunded->fresh()->status);
        $this->assertSame('live', $open->fresh()->status);
    }

    /** V1 default: paid Drops are paused until a failed-target policy exists. */
    public function test_drops_are_hidden_and_unpayable_while_disabled(): void
    {
        config(['services.drops.enabled' => false]);
        $drop = $this->liveDrop();
        $pending = CustomOrder::create([
            'full_name' => 'A', 'phone' => '254712345678', 'whatsapp_number' => '254712345678',
            'type' => 'drop', 'drop_id' => $drop->id, 'status' => 'Pending Payment',
            'price_quoted' => 8500, 'is_paid' => false,
        ]);

        $this->get(route('drops'))->assertNotFound();
        $this->get(route('drops.show', $drop))->assertNotFound();
        $this->postJson(route('drops.reserve', $drop), ['full_name' => 'A', 'phone' => '0712345678'])->assertNotFound();
        $this->withSession(['serana.order_ids' => [$pending->id]])
            ->postJson(route('mpesa.stk'), ['order_id' => $pending->id, 'phone' => '0712345678'])
            ->assertStatus(409);
        $this->assertSame(0, $pending->paymentAttempts()->count());
        $this->get(route('sitemap'))->assertDontSee('/drops');
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('featuredDrop', null)->where('drops_enabled', false));
    }
}
