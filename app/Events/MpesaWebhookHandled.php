<?php

namespace App\Events;

use App\Models\CustomOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MpesaWebhookHandled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $payload;

    /**
     * Create a new event instance.
     */
    public function __construct(CustomOrder $order, array $payload)
    {
        $this->order = $order;
        $this->payload = $payload;
    }
}
