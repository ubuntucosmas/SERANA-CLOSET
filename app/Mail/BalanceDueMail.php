<?php

namespace App\Mail;

use App\Models\CustomOrder;
use App\Services\OrderSettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase C: a shop order was paid short. Tells the customer what arrived,
 * what is still due, that the pieces are held for a day, and gives the pay
 * link for the balance.
 */
class BalanceDueMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public CustomOrder $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Balance due — {$this->order->cipher}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.balance-due', with: [
            'order' => $this->order,
            'paid' => (int) $this->order->amount_paid,
            'total' => (int) $this->order->chargeable_total,
            'balance' => (int) $this->order->balance_due,
            'hours' => OrderSettlementService::BALANCE_HOLD_HOURS,
            'payLink' => $this->order->payLink(),
            'storeName' => \App\Support\StoreProfile::name(),
        ]);
    }
}
