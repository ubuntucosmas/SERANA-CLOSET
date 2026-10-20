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
 * Phase C: tells a customer their payment arrived after the hold on an item
 * lapsed and the item has since sold out, what the studio can offer, and
 * that it becomes a full refund if nothing is decided in time.
 */
class StockGoneMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tries = 3;

    /** @param list<string> $items */
    public function __construct(public CustomOrder $order, public array $items)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "About your payment — {$this->order->cipher}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.stock-gone', with: [
            'order' => $this->order,
            'items' => $this->items,
            'paid' => (int) $this->order->amount_paid,
            'days' => OrderPolicy::resolutionDays(),
            'refundDays' => OrderPolicy::refundWorkingDays(),
            'storeName' => \App\Support\StoreProfile::name(),
            'storeWhatsApp' => \App\Support\StoreProfile::whatsapp(),
        ]);
    }
}
