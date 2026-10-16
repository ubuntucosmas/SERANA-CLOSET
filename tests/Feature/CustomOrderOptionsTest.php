<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\CustomOrderOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomOrderOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'garments' => [
                ['key' => 'set', 'label' => 'Tracksuit Set', 'enabled' => true],
                ['key' => 'hoodie', 'label' => 'Hoodie', 'enabled' => true],
                ['key' => 'jogger', 'label' => 'Joggers', 'enabled' => false],
            ],
            'fabrics' => ['Cotton Fleece', 'French Terry'],
            'colours' => [['name' => 'Forest', 'hex' => '#1F4D2B']],
        ], $overrides);
    }

    public function test_the_form_offers_sets_and_single_pieces_by_default(): void
    {
        $this->get(route('custom-order'))->assertInertia(fn (Assert $page) => $page
            ->component('CustomOrder')
            ->where('options.garments.0.key', 'set')
            ->where('options.garments.0.label', 'Hoodie & Jogger Set')
            ->where('options.garments.1.key', 'hoodie')
            ->where('options.garments.2.key', 'jogger')
            ->has('options.garments', 3)
            ->has('options.fabrics')
            ->has('options.colours'));
    }

    public function test_studio_changes_reach_the_form_and_switched_off_garments_disappear(): void
    {
        $this->actingAs($this->admin())->put(route('admin.custom_order_options.update'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->get(route('custom-order'))->assertInertia(fn (Assert $page) => $page
            ->has('options.garments', 2)
            ->where('options.garments.0.label', 'Tracksuit Set')
            ->where('options.fabrics', ['Cotton Fleece', 'French Terry'])
            ->where('options.colours.0', ['name' => 'Forest', 'hex' => '#1f4d2b']));

        // Studio still sees all three, including the one switched off.
        $this->assertCount(3, CustomOrderOptions::all()['garments']);
    }

    public function test_only_offered_choices_are_accepted_on_a_custom_order(): void
    {
        $this->actingAs($this->admin())->put(route('admin.custom_order_options.update'), $this->payload());
        auth()->logout();

        $this->postJson(route('custom-orders.store'), [
            'full_name' => 'Jane', 'whatsapp_number' => '254700000000',
            'outfit_type' => 'Gown', 'fabric_preference' => 'Silk', 'selected_color' => 'Pink',
        ])->assertStatus(422)->assertJsonValidationErrors(['outfit_type', 'fabric_preference', 'selected_color']);

        // Switched-off garment is refused too.
        $this->postJson(route('custom-orders.store'), ['full_name' => 'Jane', 'whatsapp_number' => '254700000000', 'outfit_type' => 'Joggers'])
            ->assertStatus(422)->assertJsonValidationErrors(['outfit_type']);

        $this->postJson(route('custom-orders.store'), [
            'full_name' => 'Jane', 'whatsapp_number' => '254700000000',
            'outfit_type' => 'Tracksuit Set', 'fabric_preference' => 'French Terry', 'selected_color' => 'Forest',
        ])->assertOk();
        $this->assertDatabaseHas('custom_orders', ['type' => 'Tracksuit Set', 'fabric_preference' => 'French Terry']);
    }

    public function test_garments_cannot_be_added_and_one_must_stay_on(): void
    {
        $admin = $this->admin();
        $extra = $this->payload();
        $extra['garments'][] = ['key' => 'gown', 'label' => 'Gown', 'enabled' => true];
        $this->actingAs($admin)->put(route('admin.custom_order_options.update'), $extra)->assertSessionHasErrors('garments');

        $allOff = $this->payload();
        foreach ($allOff['garments'] as &$g) $g['enabled'] = false;
        $this->actingAs($admin)->put(route('admin.custom_order_options.update'), $allOff)->assertSessionHasErrors('garments');
    }

    public function test_fabric_and_colour_lists_are_validated(): void
    {
        $this->actingAs($this->admin())->put(route('admin.custom_order_options.update'), $this->payload([
            'fabrics' => ['Fleece', 'fleece'],
            'colours' => [['name' => 'Red', 'hex' => 'red']],
        ]))->assertSessionHasErrors(['fabrics.1', 'colours.0.hex']);
    }

    public function test_only_admins_can_change_options(): void
    {
        $this->put(route('admin.custom_order_options.update'), $this->payload())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->put(route('admin.custom_order_options.update'), $this->payload())->assertRedirect();

        $this->assertSame('Hoodie & Jogger Set', CustomOrderOptions::all()['garments'][0]['label']);
    }

    public function test_made_to_measure_link_preselects_the_products_garment(): void
    {
        $product = Product::forceCreate(['name' => 'Fleece Hoodie', 'slug' => 'fleece-hoodie', 'garment_type' => 'hoodie', 'description' => 'x', 'price' => 2500, 'in_stock' => true]);
        $this->get(route('custom-order', ['product' => $product->slug]))->assertInertia(fn (Assert $page) => $page
            ->where('baseProduct.garment_type', 'hoodie')
            ->where('options.garments.1.key', 'hoodie'));
    }
}
