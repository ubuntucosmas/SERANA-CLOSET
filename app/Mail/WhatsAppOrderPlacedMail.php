<?php

namespace App\Mail;

use App\Models\CustomOrder;
use App\Support\PaymentSettings;
use App\Support\WhatsAppOrderMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "We've received your order": sent when a WhatsApp order is placed, with the
 * order code, how to pay and a one-tap WhatsApp link, so the order is never
 * lost if the customer doesn't press Send. The owner gets a Bcc copy as the
 * new-order alert. The receipt still follows once the studio confirms payment.
 */
class WhatsAppOrderPlacedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public CustomOrder $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your Serana order {$this->order->cipher}: how to pay");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.whatsapp-order-placed',
            with: [
                'order' => $this->order,
                'items' => array_values(array_filter($this->order->items_json ?? [], fn ($i) => is_array($i) && ! empty($i['name']))),
                'instructions' => PaymentSettings::instructions(),
                'holdHours' => PaymentSettings::holdHours(),
                'whatsappUrl' => WhatsAppOrderMessage::link($this->order),
                'trackUrl' => route('track', ['ref' => $this->order->cipher]),
            ],
        );
    }
}
