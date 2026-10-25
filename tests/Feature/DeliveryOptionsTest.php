<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\DeliveryOption;
use App\Models\Location;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\OrderSettlementService;
use App\Support\Actor;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase D2 (design §13.7): the customer chooses how the order reaches them.
 * The server prices it (a zone's fee, free over a threshold, a shop pickup at
 * no charge, or a quote on WhatsApp the Studio prices later) and writes a
 * delivery line, so every total shows it. Pickup orders run ready for
 * pickup → collected; delivery orders ship.
 */
class DeliveryOptionsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\StockFixtures;
    use \Tests\Concerns\DeliveryFixtures;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->create(['role' => Role::OWNER]);
    }

    private function checkout(array $extra, int $price = 5000)
    {
        $product = $this->stockedProduct(3, ['name' => 'Linen Set', 'price' => $price]);

        return $this->postJson(route('checkout.process'), array_merge(['full_name' => 'Jane Wanjiru', 'email' => 'jane@example.com', 'phone' => '0712345678',
            'address' => 'Moi Ave 1', 'city' => 'Nairobi', 'items' => [['id' => $product->id, 'quantity' => 1, 'options' => ['top_size' => 'One size']]]], $extra));
    }

    public function test_the_shop_starts_with_pickup_and_a_whatsapp_quote_and_the_owner_adds_zones(): void
    {
        $this->assertSame(['pickup', 'quote'], DeliveryOption::orderBy('sort')->pluck('type')->all());

        $this->actingAs($this->owner)->post(route('studio.delivery.store'), ['name' => 'Nairobi CBD', 'type' => 'zone', 'fee' => 300, 'free_over' => 10000,
            'eta_text' => 'Next working day', 'reason' => 'Our rider covers town'])->assertSessionHasNoErrors();
        $zone = DeliveryOption::where('name', 'Nairobi CBD')->sole();
        $this->actingAs($this->owner)->put(route('studio.delivery.update', $zone), ['name' => 'Nairobi CBD', 'type' => 'zone', 'fee' => 350, 'free_over' => 10000,
            'eta_text' => 'Next working day', 'active' => false, 'reason' => 'Rider unavailable'])->assertSessionHasNoErrors();
        $this->assertSame([350, false], [$zone->fresh()->fee, $zone->fresh()->active]);
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'like', 'delivery.%')->count());

        $manager = User::factory()->create(['role' => Role::MANAGER]);
        $this->actingAs($manager)->post(route('studio.delivery.store'), ['name' => 'X', 'type' => 'zone', 'fee' => 1, 'reason' => 'x'])->assertForbidden();
    }

    public function test_a_zone_fee_is_a_delivery_line_in_the_total_and_free_over_the_threshold(): void
    {
        $zone = $this->deliveryZone(fee: 300, freeOver: 6000);

        $res = $this->checkout(['delivery_option_id' => $zone->id])->assertOk();
        $order = CustomOrder::findOrFail($res->json('order_id'));
        $this->assertSame([5300, 5300], [$order->chargeable_total, $res->json('amount')]);
        $line = $order->lines()->where('line_type', OrderLine::DELIVERY)->sole();
        $this->assertSame(['Delivery: Nairobi CBD', 300], [$line->description, $line->amount]);
        $this->assertSame(['delivery', $zone->id], [$order->fulfilment_method, $order->delivery_option_id]);
        $this->assertSame(['name' => 'Nairobi CBD', 'fee' => 300, 'eta' => 'Next working day'], $order->deliverySummary());

        // At the threshold it is free, and still shown.
        $free = CustomOrder::findOrFail($this->checkout(['delivery_option_id' => $zone->id], 6000)->json('order_id'));
        $this->assertSame([6000, 0], [$free->chargeable_total, $free->lines()->where('line_type', OrderLine::DELIVERY)->sole()->amount]);
    }

    public function test_a_tampered_fee_is_ignored_and_an_inactive_option_refused(): void
    {
        $zone = $this->deliveryZone(fee: 300);
        $order = CustomOrder::findOrFail($this->checkout(['delivery_option_id' => $zone->id, 'delivery_fee' => 0, 'fee' => 0])->json('order_id'));
        $this->assertSame(5300, $order->chargeable_total);

        $zone->update(['active' => false]);
        $this->checkout(['delivery_option_id' => $zone->id])->assertUnprocessable()->assertJsonValidationErrors('delivery_option_id');
        $this->checkout([])->assertUnprocessable()->assertJsonValidationErrors('delivery_option_id');
    }

    public function test_pickup_needs_no_address_costs_nothing_and_names_the_shop(): void
    {
        Location::query()->update(['address' => 'Moi Avenue, Shop 4', 'accepts_pickup' => true]);
        $pickup = DeliveryOption::where('type', 'pickup')->sole();

        $order = CustomOrder::findOrFail($this->checkout(['delivery_option_id' => $pickup->id, 'address' => null, 'city' => null])->assertOk()->json('order_id'));

        $this->assertSame(['pickup', 5000, null], [$order->fulfilment_method, $order->chargeable_total, $order->address]);
        $this->assertSame(0, $order->lines()->where('line_type', OrderLine::DELIVERY)->sole()->amount);
        $this->assertSame('Moi Avenue, Shop 4', $order->fulfilment_snapshot['address']);
    }

    public function test_pickup_is_not_offered_until_the_shop_has_an_address(): void
    {
        Location::query()->update(['address' => null]);
        $pickup = DeliveryOption::where('type', 'pickup')->sole();

        $this->checkout(['delivery_option_id' => $pickup->id])->assertUnprocessable()->assertJsonValidationErrors('delivery_option_id');
        $this->assertNotContains($pickup->id, collect($this->get(route('checkout'))->viewData('page')['props']['delivery_options'])->pluck('id')->all());
    }

    public function test_a_quote_goes_to_whatsapp_and_cant_be_paid_until_the_studio_prices_delivery(): void
    {
        $quote = DeliveryOption::where('type', 'quote')->sole();
        $order = CustomOrder::findOrFail($this->checkout(['delivery_option_id' => $quote->id, 'payment_method' => 'mpesa'])->assertOk()->json('order_id'));

        $this->assertSame(['whatsapp', 5000, true], [$order->payment_method, $order->chargeable_total, $order->needsDeliveryQuote()]);
        $this->assertSame(0, $order->lines()->where('line_type', OrderLine::DELIVERY)->count());
        $this->get(\Illuminate\Support\Facades\URL::signedRoute('pay', $order))->assertInertia(fn ($p) => $p->where('order.needs_delivery_quote', true));
        $this->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678'])->assertUnprocessable()
            ->assertJsonPath('message', 'The Studio is pricing your delivery. You will get the total on WhatsApp before you pay.');

        // Nor can the Studio confirm money against a total that isn't known yet.
        $this->actingAs($this->owner)->post(route('studio.order.record_payment', $order), ['amount' => 5000, 'method' => 'mpesa_manual', 'reference' => 'QBEFOREQUOTE'])
            ->assertSessionHasErrors(['amount' => 'Price the delivery first. The total isn’t known yet.']);
        $this->assertSame(0, $order->payments()->count());
        // Opened from a link, the Studio shows what must happen before the customer can pay.
        $focus = $this->actingAs($this->owner)->get(route('studio.index', ['tab' => 'orders', 'order' => $order->id]))->viewData('page')['props']['focus_order'];
        $this->assertTrue($focus['delivery_info']['needs_quote']);
        $this->actingAs($this->owner)->post(route('studio.order.delivery', $order), ['delivery_option_id' => $quote->id, 'fee' => 800, 'reason' => 'Rider to Thika quoted'])
            ->assertSessionHasNoErrors();
        $fresh = $order->fresh();
        $this->assertSame([5800, false], [$fresh->chargeable_total, $fresh->needsDeliveryQuote()]);
        $this->assertSame('Rider to Thika quoted', $fresh->lines()->where('line_type', OrderLine::DELIVERY)->sole()->reason);
    }

    public function test_the_studio_list_reads_delivery_and_payments_the_same_from_loaded_rows(): void
    {
        $quote = DeliveryOption::where('type', 'quote')->sole();
        $order = CustomOrder::findOrFail($this->checkout(['delivery_option_id' => $quote->id])->assertOk()->json('order_id'));
        $read = fn (CustomOrder $o) => [$o->deliveryTotal(), $o->needsDeliveryQuote(), $o->quoteAmount(), $o->delivery_info, $o->ledger_rows];
        $loaded = fn () => CustomOrder::with(['lines', 'payments.attempt', 'deliveryOption'])->findOrFail($order->id);
        $this->assertSame($read($order->fresh()), $read($loaded()), 'quote not priced yet');

        $this->actingAs($this->owner)->post(route('studio.order.delivery', $order), ['delivery_option_id' => $quote->id, 'fee' => 800, 'reason' => 'Rider quoted'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('studio.order.record_payment', $order), ['amount' => 5800, 'method' => 'mpesa_manual', 'reference' => 'QLOADED001'])->assertSessionHasNoErrors();
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame($read($order->fresh()), $read($loaded()), 'priced and paid');
        $this->assertSame([800, false], [$loaded()->deliveryTotal(), $loaded()->needsDeliveryQuote()]);
    }

    public function test_the_studio_adds_delivery_to_a_bespoke_quote_and_can_change_it_until_it_ships(): void
    {
        $zone = $this->deliveryZone(fee: 500);
        $order = CustomOrder::create(['full_name' => 'Amina', 'whatsapp_number' => '254712345678', 'type' => 'Gown']);
        app(OrderSettlementService::class)->setQuote($order, 12000, Actor::user($this->owner), 'Quoted');

        $this->actingAs($this->owner)->post(route('studio.order.delivery', $order), ['delivery_option_id' => $zone->id, 'reason' => 'Customer is in town'])->assertSessionHasNoErrors();
        $this->assertSame([12500, 6250], [$order->fresh()->chargeable_total, $order->fresh()->depositRequired()]);

        $pickup = DeliveryOption::where('type', 'pickup')->sole();
        Location::query()->update(['address' => 'Moi Avenue, Shop 4', 'accepts_pickup' => true]);
        $this->actingAs($this->owner)->post(route('studio.order.delivery', $order), ['delivery_option_id' => $pickup->id, 'reason' => 'She will collect'])->assertSessionHasNoErrors();
        $this->assertSame([12000, 'pickup'], [$order->fresh()->chargeable_total, $order->fresh()->fulfilment_method]);
        $this->assertSame(['Delivery: Nairobi CBD' => 500, 'Delivery change: Collect from the shop' => -500],
            $order->lines()->whereIn('line_type', [OrderLine::DELIVERY, OrderLine::ADJUSTMENT])->pluck('amount', 'description')->all());

        CustomOrder::whereKey($order->id)->update(\App\Support\LegacyOrderMap::forStatus('collected'));
        $this->actingAs($this->owner)->post(route('studio.order.delivery', $order), ['delivery_option_id' => $zone->id, 'reason' => 'x'])
            ->assertSessionHasErrors(['delivery_option_id' => 'This order was already handed over. Its delivery can’t change now.']);
    }

    public function test_a_pickup_order_is_made_ready_then_collected_and_never_shipped(): void
    {
        Location::query()->update(['address' => 'Moi Avenue, Shop 4', 'accepts_pickup' => true]);
        $order = CustomOrder::findOrFail($this->checkout(['delivery_option_id' => DeliveryOption::where('type', 'pickup')->value('id'), 'address' => null, 'city' => null])->json('order_id'));
        app(OrderSettlementService::class)->recordPayment($order, null, 'mpesa_stk', Actor::safaricom(), 'QPICKUP1');

        $this->actingAs($this->owner)->put(route('studio.update', $order), ['status' => 'shipped', 'internal_notes' => null])
            ->assertSessionHasErrors(['status' => 'This order is collected from the shop. Mark it ready for pickup instead.']);
        $this->actingAs($this->owner)->put(route('studio.update', $order), ['status' => 'ready_for_pickup', 'internal_notes' => null])->assertSessionHasNoErrors();

        $this->assertSame('ready_for_pickup', $order->fresh()->fulfilment_status);
        Mail::assertQueued(\App\Mail\ReadyForPickupMail::class, fn ($m) => $m->hasTo('jane@example.com') && $m->order->is($order));
        $this->assertSame('Ready for collection. Bring your order reference and the phone number used for this order. Any balance must be settled before handover.',
            $order->fresh()->handoverDetails()['next']);
    }

    public function test_a_delivery_order_is_never_ready_for_pickup(): void
    {
        $order = CustomOrder::findOrFail($this->checkout(['delivery_option_id' => $this->deliveryZone()->id])->json('order_id'));
        app(OrderSettlementService::class)->recordPayment($order, null, 'mpesa_stk', Actor::safaricom(), 'QDELIV1');

        $this->actingAs($this->owner)->put(route('studio.update', $order), ['status' => 'ready_for_pickup', 'internal_notes' => null])
            ->assertSessionHasErrors(['status' => 'This order is delivered. It ships; it isn’t collected from the shop.']);
    }

    public function test_receipts_tracking_and_the_whatsapp_message_show_the_delivery_line(): void
    {
        $order = CustomOrder::findOrFail($this->checkout(['delivery_option_id' => $this->deliveryZone(fee: 300)->id])->json('order_id'));
        app(OrderSettlementService::class)->recordPayment($order, null, 'mpesa_stk', Actor::safaricom(), 'QSHOWN1');
        $order->refresh();

        $this->assertSame(['name' => 'Nairobi CBD', 'fee' => 300, 'eta' => 'Next working day'], $order->publicTrackingPayload()['delivery']);
        $this->assertStringContainsString('Delivery (Nairobi CBD): KSh 300', \App\Support\WhatsAppOrderMessage::text($order));
        $receipt = (new \App\Mail\OrderReceiptMail($order, $order->payments()->first()))->render();
        $this->assertStringContainsString('Nairobi CBD', $receipt);
        $this->assertStringContainsString('KSh 300', $receipt);
    }
}
