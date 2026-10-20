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
 * The receipt for one payment (design B): sent to the customer (owner in
 * Bcc) for every payment, deposit or balance, on every kind of order. It
 * shows the total, this payment, the amount paid to date and the balance,
 * all as of this payment, and attaches the same figures as a PDF.
 */
class OrderReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public const METHODS = [
        'mpesa_stk' => 'M-Pesa',
        'mpesa_manual' => 'M-Pesa (recorded by the studio)',
        'cash' => 'Cash',
        'bank' => 'Bank transfer',
        'card' => 'Card',
        'credit' => 'Store credit',
    ];

    public function __construct(public CustomOrder $order, public ?OrderPayment $payment = null)
    {
        $this->payment ??= $order->payments()->where('type', OrderPayment::PAYMENT)->latest('id')->first();
    }

    /** Everything the email and the PDF show, as of $payment. */
    public static function viewData(CustomOrder $order, ?OrderPayment $payment): array
    {
        $sums = $order->ledgerSums($payment?->id);
        $total = (int) $order->chargeable_total;
        $paidToDate = $sums['payments'] - $sums['refunds'];
        $balance = $total - $paidToDate;

        return [
            'order' => $order,
            // Real line items only: raw items_json also carries non-item keys.
            'items' => $order->items_with_urls,
            'payment' => $payment,
            'methodLabel' => $payment ? (self::METHODS[$payment->method] ?? $payment->method) : null,
            'receiptNumber' => $payment
                ? $order->cipher . '-P' . $order->payments()->where('type', OrderPayment::PAYMENT)->where('id', '<=', $payment->id)->count()
                : $order->cipher,
            'total' => $total,
            'paidToDate' => $paidToDate,
            'balance' => max(0, $balance),
            'excess' => max(0, -$balance),
            // This payment arrived on an order that was already settled.
            'paidTwice' => $payment && $total > 0 && $total - ($paidToDate - $payment->amount) <= 0,
            'refundDays' => \App\Support\OrderPolicy::refundWorkingDays(),
            // Paid, but an item sold out before the money arrived (Phase C): not a confirmed order.
            'unavailable' => collect($order->stock_problem['items'] ?? [])->pluck('name')->unique()->values()->all(),
            'paidInFull' => $total > 0 && $balance <= 0,
            'depositPolicy' => $order->isBespoke() ? \App\Support\OrderPolicy::depositPolicy() : null,
            'trackUrl' => URL::route('track', ['ref' => $order->cipher]),
            'date' => $payment
                ? $payment->occurred_at->copy()->timezone(config('app.display_timezone'))->format('M d, Y g:i A')
                : $order->paidAtForDisplay(),
        ];
    }

    private function data(): array
    {
        return self::viewData($this->order, $this->payment);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->data()['paidInFull'] && ! $this->data()['unavailable']
                ? "Your Serana receipt — {$this->order->cipher}"
                : "Payment received — {$this->order->cipher}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-receipt', with: $this->data());
    }

    public function attachments(): array
    {
        $data = $this->data();
        $pdf = Pdf::loadView('receipts.luxury', $data);

        return [
            Attachment::fromData(fn () => $pdf->output(), "Serana-Receipt-{$data['receiptNumber']}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
