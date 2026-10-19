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
 * Owner alert: a bespoke M-Pesa payment arrived for a different amount than
 * the prompt asked. It was recorded as received, but customers cannot change
 * an STK amount, so a difference signals a bug or tampering. Queued, after
 * commit: it is raised inside Safaricom's callback.
 */
class UnexpectedAmountAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public CustomOrder $order, public int $prompted, public int $paid, public ?string $reference)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "⚠ Unexpected M-Pesa amount — order {$this->order->cipher}");
    }

    public function content(): Content
    {
        return new Content(text: 'emails.unexpected-amount-alert');
    }
}
