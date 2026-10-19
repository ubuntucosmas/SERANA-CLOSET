<?php

namespace App\Mail;

use App\Models\CustomOrder;
use App\Support\StoreProfile;
use App\Support\WhatsApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Owner alert for a new custom brief. The customer is sent on to WhatsApp, but
 * if they never press Send the brief would only be found by opening the Studio;
 * this puts it in the owner's inbox with one tap to quote it.
 */
class NewCustomOrderAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public CustomOrder $order, public string $brief)
    {
    }

    public function envelope(): Envelope
    {
        $flag = $this->order->priority_pass ? 'Priority ' : '';

        return new Envelope(subject: "{$flag}custom order {$this->order->cipher} from {$this->order->full_name}: needs a quote");
    }

    public function content(): Content
    {
        $customer = WhatsApp::normalizeNumber((string) $this->order->whatsapp_number);

        return new Content(
            view: 'emails.new-custom-order-alert',
            with: [
                'order' => $this->order,
                'brief' => $this->brief,
                'storeName' => StoreProfile::name(),
                'studioUrl' => route('studio.index', ['tab' => 'orders', 'order' => $this->order->id]),
                'customerWhatsApp' => $customer ? 'https://wa.me/' . $customer : null,
                'customerDisplay' => $customer ? StoreProfile::display($customer) : (string) $this->order->whatsapp_number,
            ],
        );
    }
}
