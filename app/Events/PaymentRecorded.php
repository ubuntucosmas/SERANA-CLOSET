<?php

namespace App\Events;

use App\Models\CustomOrder;
use App\Models\OrderPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A payment was booked on an order (design B): fired after commit by
 * OrderSettlementService for every payment, deposit or balance, on every
 * kind of order. The receipt listener sends that payment's receipt.
 */
class PaymentRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(public CustomOrder $order, public OrderPayment $payment, public array $payload = [])
    {
    }
}
