<?php

namespace App\Mail;

use App\Models\CustomOrder;
use App\Models\OrderPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The receipt for one refund (Phase C): what was sent back, how, its code,
 * and what is still to be refunded, all as of this refund. Sent to the
 * customer (owner in Bcc) with the same figures as a PDF.
 */
class RefundReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public CustomOrder $order, public OrderPayment $refund)
    {
        $this->afterCommit();
    }

    /** Everything the email and the PDF show, as of $refund. */
    public static function viewData(CustomOrder $order, OrderPayment $refund): array
    {
        $sums = $order->ledgerSums($refund->id);
        $total = (int) $order->chargeable_total;
        $net = $sums['payments'] - $sums['refunds'];

        return [
            'order' => $order,
            'refund' => $refund,
            'methodLabel' => OrderReceiptMail::METHODS[$refund->method] ?? $refund->method,
            'receiptNumber' => $order->cipher . '-R' . $order->payments()->where('type', OrderPayment::REFUND)->where('id', '<=', $refund->id)->count(),
            'total' => $total,
            'received' => $sums['payments'],
            'refunded' => $sums['refunds'],
            'stillToRefund' => max(0, $net - $total),
            'trackUrl' => URL::route('track', ['ref' => $order->cipher]),
            'date' => $refund->occurred_at->copy()->timezone(config('app.display_timezone'))->format('M d, Y g:i A'),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Refund sent — {$this->order->cipher}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.refund-receipt', with: self::viewData($this->order, $this->refund));
    }

    public function attachments(): array
    {
        $data = self::viewData($this->order, $this->refund);
        $pdf = Pdf::loadView('receipts.refund', $data);

        return [
            Attachment::fromData(fn () => $pdf->output(), "Serana-Refund-{$data['receiptNumber']}.pdf")->withMime('application/pdf'),
        ];
    }
}
