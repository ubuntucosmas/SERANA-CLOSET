<?php

namespace Tests\Feature;

use App\Mail\UnexpectedAmountAlertMail;
use App\Models\CustomOrder;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A bespoke M-Pesa payment whose amount differs from the prompt is recorded
 * as received, and (customers can't change an STK amount, so it signals a
 * bug or tampering) shown on the Studio Attention list as "unexpected amount"
 * with an email alert queued to the owner.
 */
class UnexpectedAmountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.mpesa.callback_token' => 'secret', 'services.store.notification_email' => 'owner@serana.test']);
        Http::fake(['*oauth*' => Http::response(['access_token' => 't']), '*processrequest*' => Http::response(['CheckoutRequestID' => 'ws_U'])]);
    }

    private function promptedBespoke(): CustomOrder
    {
        $order = CustomOrder::create(['full_name' => 'Amina Otieno', 'whatsapp_number' => '254712345678', 'type' => 'Gown', 'status' => 'pending', 'price_quoted' => 10000]);
        $this->withSession(['serana.order_ids' => [$order->id]])
            ->postJson(route('mpesa.stk'), ['order_id' => $order->id, 'phone' => '0712345678', 'pay' => 'deposit'])->assertOk();

        return $order;
    }

    private function pay(int $amount): void
    {
        $this->postJson(route('mpesa.callback', ['token' => 'secret']), ['Body' => ['stkCallback' => [
            'ResultCode' => 0, 'CheckoutRequestID' => 'ws_U', 'CallbackMetadata' => ['Item' => [
                ['Name' => 'Amount', 'Value' => $amount], ['Name' => 'MpesaReceiptNumber', 'Value' => 'RUNEXP1'],
            ]],
        ]]])->assertOk();
    }

    private function attention(): array
    {
        return $this->actingAs(User::factory()->create(['role' => Role::OWNER]))->get(route('studio.index'))
            ->viewData('page')['props']['attention']['awaiting'];
    }

    public function test_a_different_amount_is_recorded_flagged_for_the_studio_and_alerted(): void
    {
        $order = $this->promptedBespoke();

        $this->pay(3000);

        // Still recorded as received.
        $this->assertSame([3000, 'deposit_paid'], [$order->fresh()->amount_paid, $order->fresh()->payment_status]);

        // On the Attention list, with what was paid against what was asked.
        $item = collect($this->attention())->firstWhere('reference', $order->cipher);
        $this->assertNotNull($item, 'the order is on the Attention list');
        $this->assertSame('Unexpected amount: KSh 3,000 paid against a KSh 5,000 prompt', $item['next_action']);

        // And the owner is told.
        Mail::assertQueued(UnexpectedAmountAlertMail::class, fn ($mail) => $mail->hasTo('owner@serana.test')
            && $mail->order->is($order) && $mail->prompted === 5000 && $mail->paid === 3000 && $mail->reference === 'RUNEXP1');
    }

    public function test_the_alert_explains_why_it_matters(): void
    {
        $order = $this->promptedBespoke();
        $mail = new UnexpectedAmountAlertMail($order, 5000, 3000, 'RUNEXP1');

        $mail->assertHasSubject("⚠ Unexpected M-Pesa amount — order {$order->cipher}");
        $mail->assertSeeInText('KSh 3,000 was paid against a KSh 5,000 prompt');
        $mail->assertSeeInText('RUNEXP1');
        $mail->assertSeeInText('recorded as received');
        $mail->assertSeeInText('bug or tampering');
        $this->assertSame(3, $mail->tries);
    }

    public function test_the_exact_amount_raises_nothing(): void
    {
        $order = $this->promptedBespoke();

        $this->pay(5000);

        Mail::assertNotQueued(UnexpectedAmountAlertMail::class);
        $this->assertNull(collect($this->attention())->first(fn ($item) => str_starts_with($item['next_action'], 'Unexpected amount')));
    }

    public function test_without_an_owner_inbox_it_is_still_flagged_in_the_studio(): void
    {
        config(['services.store.notification_email' => null, 'mail.from.address' => null]);
        $order = $this->promptedBespoke();

        $this->pay(3000);

        Mail::assertNotQueued(UnexpectedAmountAlertMail::class);
        $this->assertNotNull(collect($this->attention())->first(fn ($item) => $item['reference'] === $order->cipher
            && str_starts_with($item['next_action'], 'Unexpected amount')));
    }
}
