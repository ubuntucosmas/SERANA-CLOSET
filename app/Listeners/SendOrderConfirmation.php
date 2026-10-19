<?php

namespace App\Listeners;

use App\Events\MpesaWebhookHandled;
use App\Mail\OrderReceiptMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the paid-order receipt to the customer and copies the owner, the
 * instant payment is confirmed. Runs off the MpesaWebhookHandled event so it
 * fires for both the live callback and the reconciliation job.
 *
 * Queued: the event fires inside Safaricom's callback request, and a slow SMTP
 * round-trip there would make Safaricom time out and retry the callback.
 *
 * Mail delivery is best-effort: a mail failure must never break payment
 * settlement (the order is already paid), so all errors are caught and logged.
 */
class SendOrderConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 3;
    public $timeout = 60;

    public function backoff(): array { return [30, 120]; }

    public function handle(MpesaWebhookHandled $event): void
    {
        $order = $event->order;

        $ownerEmail = \App\Support\StoreProfile::notificationEmail();

        try {
            if ($order->email) {
                // Customer is primary; owner gets a Bcc copy for their records.
                $mailer = Mail::to($order->email);
                if ($ownerEmail) {
                    $mailer->bcc($ownerEmail);
                }
                $mailer->send(new OrderReceiptMail($order));
            } elseif ($ownerEmail) {
                // No customer email (e.g. a bespoke order) — owner still gets it.
                Mail::to($ownerEmail)->send(new OrderReceiptMail($order));
            }
        } catch (\Throwable $e) {
            Log::error('Order receipt delivery failed; queued retry required.', ['order_id' => $order->id, 'exception' => get_class($e)]);
            throw $e;
        }
    }
}
