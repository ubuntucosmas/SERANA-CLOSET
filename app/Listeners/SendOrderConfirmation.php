<?php

namespace App\Listeners;

use App\Events\MpesaWebhookHandled;
use App\Mail\OrderReceiptMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the paid-order receipt to the customer and copies the owner, the
 * instant payment is confirmed. Runs off the MpesaWebhookHandled event so it
 * fires for both the live callback and the reconciliation job.
 *
 * Mail delivery is best-effort: a mail failure must never break payment
 * settlement (the order is already paid), so all errors are caught and logged.
 */
class SendOrderConfirmation
{
    public function handle(MpesaWebhookHandled $event): void
    {
        $order = $event->order;

        $ownerEmail = config('services.store.notification_email')
            ?: config('mail.from.address');

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
            Log::error("Order receipt email failed for Order #{$order->id}: " . $e->getMessage());
        }
    }
}
