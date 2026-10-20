<?php

namespace App\Mail;

use App\Models\CustomOrder;
use App\Support\OrderPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase C (N8): a custom order was cancelled. States which rule applied: the
 * deposit refunded (before designing) or kept (from designing on), and any
 * amount owed back.
 */
class BespokeCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public CustomOrder $order, public int $retained, public int $owedBack)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your order is cancelled — {$this->order->cipher}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bespoke-cancelled', with: [
            'order' => $this->order,
            'retained' => $this->retained,
            'owedBack' => $this->owedBack,
            'refundDays' => OrderPolicy::refundWorkingDays(),
            'policy' => OrderPolicy::depositPolicy(),
            // The stage work had reached, kept on the order.
            'stage' => ucfirst((string) $this->order->fulfilment_status),
            'storeName' => \App\Support\StoreProfile::name(),
        ]);
    }
}
