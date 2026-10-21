<?php

namespace Tests\Concerns;

use App\Models\DeliveryOption;

/**
 * Phase D2: every checkout names a delivery option. A free zone keeps the
 * totals of tests written before delivery had a price.
 */
trait DeliveryFixtures
{
    protected function deliveryZone(int $fee = 300, ?int $freeOver = null, string $name = 'Nairobi CBD'): DeliveryOption
    {
        return DeliveryOption::firstOrCreate(['name' => $name, 'type' => 'zone'],
            ['fee' => $fee, 'free_over' => $freeOver, 'eta_text' => 'Next working day', 'active' => true, 'sort' => 10]);
    }

    /** The id of a free delivery zone, for checkouts that aren't about delivery. */
    protected function freeDelivery(): int
    {
        return $this->deliveryZone(fee: 0, name: 'Test delivery (free)')->id;
    }
}
