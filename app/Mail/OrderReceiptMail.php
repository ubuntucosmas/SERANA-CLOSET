<?php

namespace App\Mail;

use App\Models\CustomOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The paid-order receipt — sent to the customer (and copied to the owner) the
 * moment payment is confirmed. Carries the durable tracking link so the
 * customer can follow the order long after the checkout tab is closed, and
 * attaches the same luxury PDF receipt available on the tracking page.
 */
class OrderReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CustomOrder $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Serana receipt — {$this->order->cipher}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-receipt',
            with: [
                'order'    => $this->order,
                'items'    => $this->order->items_with_urls,
                'trackUrl' => URL::route('track', ['ref' => $this->order->cipher]),
                'date'     => optional($this->order->updated_at)->format('M d, Y H:i'),
            ],
        );
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('receipts.luxury', [
            'order' => $this->order,
            'items' => $this->order->items_json ?: [],
            'date'  => optional($this->order->updated_at)->format('M d, Y H:i A'),
        ]);

        return [
            Attachment::fromData(fn () => $pdf->output(), "Serana-Receipt-{$this->order->cipher}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
