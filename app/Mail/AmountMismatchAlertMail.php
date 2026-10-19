<?php

namespace App\Mail;

use App\Models\CustomOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Owner alert: an M-Pesa payment arrived for the wrong amount, so the money is
 * in the till but the order was not marked paid. Queued (and only after the
 * surrounding transaction commits) because it is raised inside Safaricom's
 * callback request, which a slow SMTP server must never delay or fail.
 */
class AmountMismatchAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public CustomOrder $order, public int $expected, public mixed $paid)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "⚠ Payment amount mismatch — order {$this->order->cipher}");
    }

    public function content(): Content
    {
        return new Content(text: 'emails.amount-mismatch-alert');
    }
}
