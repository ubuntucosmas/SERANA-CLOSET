<?php

namespace App\Mail;

use App\Models\CustomOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase D2 (design §13.7): the order is ready at the shop. Where to come,
 * what to bring (the reference and the phone number they ordered with), and
 * that any balance is settled before it is handed over.
 */
class ReadyForPickupMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public CustomOrder $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Ready for pickup — {$this->order->cipher}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ready-for-pickup', with: [
            'order' => $this->order,
            'shop' => $this->order->fulfilment_snapshot['name'] ?? 'our shop',
            'address' => $this->order->fulfilment_snapshot['address'] ?? null,
            'balance' => max(0, (int) $this->order->balance_due),
            'storeName' => \App\Support\StoreProfile::name(),
            'storeWhatsApp' => \App\Support\StoreProfile::whatsapp(),
        ]);
    }
}
