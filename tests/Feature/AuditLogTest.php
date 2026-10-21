<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CustomOrder;
use App\Models\Product;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who did what, when, for every money, stock and permission-sensitive action
 * (design §1 N3). Audit rows are append-only.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => Role::OWNER, 'name' => 'Owner']);
    }

    private function brief(array $o = []): CustomOrder
    {
        return CustomOrder::create(array_merge(['full_name' => 'Client', 'whatsapp_number' => '254712345678', 'type' => 'custom', 'status' => 'pending', 'price_quoted' => 0], $o));
    }

    public function test_a_studio_order_change_records_who_what_and_the_before_and_after(): void
    {
        $owner = $this->owner();
        $order = $this->brief();

        // Quote and notes change; the stage stays (starting work now needs the deposit, design N7).
        $this->actingAs($owner)->put(route('studio.update', $order), ['status' => 'pending', 'price_quoted' => 6000, 'internal_notes' => 'Linen'])
            ->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'order.updated')->sole();
        $this->assertSame('user', $log->actor_type);
        $this->assertSame($owner->id, $log->actor_id);
        $this->assertTrue($log->subject->is($order));
        // Loose for the uncast decimal: SQLite returns 0, MariaDB '0.00'.
        $this->assertEquals(['internal_notes' => null, 'price_quoted' => 0], $log->before);
        $this->assertSame(['internal_notes' => 'Linen', 'price_quoted' => 6000], $log->after);
    }

    public function test_an_unchanged_save_records_nothing(): void
    {
        $order = $this->brief(['status' => 'designing']);

        $this->actingAs($this->owner())->put(route('studio.update', $order), ['status' => 'designing', 'price_quoted' => 0, 'internal_notes' => null]);

        $this->assertSame(0, AuditLog::count());
    }

    public function test_recording_a_payment_is_audited_with_its_reference(): void
    {
        $order = $this->brief(['price_quoted' => 4500, 'status' => 'designing']);

        $this->actingAs($this->owner())->post(route('studio.order.record_payment', $order), ['amount' => 4500, 'method' => 'mpesa_manual', 'reference' => 'QK12ABC']);

        $log = AuditLog::where('action', 'payment.recorded')->sole();
        $this->assertSame(['amount' => 4500, 'reference' => 'QK12ABC'], $log->after);
    }

    public function test_archive_restore_and_purge_of_an_order_are_audited(): void
    {
        $owner = $this->owner();
        $order = $this->brief();

        $this->actingAs($owner)->delete(route('studio.order.destroy', $order));
        $this->actingAs($owner)->post(route('studio.order.restore', $order->id));
        $this->actingAs($owner)->delete(route('studio.order.destroy', $order));
        $this->actingAs($owner)->delete(route('studio.order.force_delete', $order->id));

        $this->assertSame(['order.archived', 'order.restored', 'order.archived', 'order.purged'],
            AuditLog::orderBy('id')->pluck('action')->all());
        // The purged order is gone, but its audit trail names it.
        $this->assertSame($order->cipher, AuditLog::where('action', 'order.purged')->sole()->before['cipher']);
    }

    /** The old model's batch limit set from the product form (with stock per size, stock changes are audited in Studio → Stock: StudioStockTest). */
    public function test_product_price_and_stock_changes_are_audited(): void
    {
        config(['serana.stock_v2' => 'off']);
        $category = Category::create(['name' => 'Sets', 'slug' => 'sets']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Set', 'slug' => 'set', 'description' => 'x', 'price' => 5000, 'batch_limit' => 10, 'in_stock' => true]);

        $this->actingAs($this->owner())->put(route('admin.catalog.update_product', $product), [
            'category_id' => $category->id, 'name' => 'Set', 'description' => 'x', 'price' => 5500, 'batch_limit' => 8, 'in_stock' => true,
        ])->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'product.updated')->sole();
        $this->assertSame(['price' => '5000.00', 'batch_limit' => 10], $log->before);
        $this->assertSame(['price' => 5500, 'batch_limit' => 8], $log->after);
    }

    public function test_payment_settings_changes_are_audited(): void
    {
        $this->actingAs($this->owner())->put(route('admin.payment_settings.update'), [
            'mpesa_enabled' => true, 'whatsapp_enabled' => true, 'instructions' => 'Till 123', 'whatsapp_hold_hours' => 24,
        ])->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'settings.payments.updated')->sole();
        $this->assertSame(24, $log->after['whatsapp_hold_hours']);
    }

    public function test_staff_account_changes_from_the_cli_are_audited_as_system(): void
    {
        $this->artisan('serana:admin', ['email' => 'm@serana.test', '--role' => 'manager', '--generate' => true])->assertSuccessful();

        $log = AuditLog::where('action', 'staff.saved')->sole();
        $this->assertSame('system', $log->actor_type);
        $this->assertNull($log->actor_id);
        $this->assertSame(['email' => 'm@serana.test', 'role' => 'manager', 'password_changed' => true], $log->after);
    }

    public function test_audit_rows_cannot_be_edited_or_deleted(): void
    {
        $order = $this->brief();
        $this->actingAs($this->owner())->delete(route('studio.order.destroy', $order));
        $log = AuditLog::sole();

        $this->expectException(\LogicException::class);
        $log->update(['action' => 'nothing.happened']);
    }

    public function test_audit_rows_cannot_be_deleted(): void
    {
        $order = $this->brief();
        $this->actingAs($this->owner())->delete(route('studio.order.destroy', $order));

        $this->expectException(\LogicException::class);
        AuditLog::sole()->delete();
    }
}
