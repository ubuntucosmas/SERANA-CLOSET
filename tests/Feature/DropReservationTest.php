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
        ]);

        $res->assertOk()->assertJson(['success' => true]);

        $order = CustomOrder::latest()->first();
        $this->assertSame('drop', $order->type);
        $this->assertSame($drop->id, $order->drop_id);
        $this->assertEquals(8500, (int) $order->price_quoted);
        $this->assertFalse((bool) $order->is_paid);
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
        $admin = User::factory()->create(['role' => 'admin']);

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
}
