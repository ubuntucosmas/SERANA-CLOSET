<?php

namespace App\Support;

use App\Models\CustomOrder;

/**
 * The message a customer sends the studio for a WhatsApp order. Written in the
 * customer's voice (they press Send), scannable on a phone: the order code
 * first, numbered items with sizes, the total, delivery, and the tracking link.
 */
class WhatsAppOrderMessage
{
    public static function text(CustomOrder $order): string
    {
        $money = fn ($v) => 'KSh ' . number_format((float) $v);
        $lines = ['Hello ' . StoreProfile::name() . ',', '', "I'd like to place this order:", "*Order code: {$order->cipher}*", ''];

        foreach (array_values($order->items_json ?? []) as $i => $item) {
            if (! is_array($item) || empty($item['name'])) continue;
            $qty = (int) ($item['quantity'] ?? 1);
            $line = ($i + 1) . ". {$item['name']} ×{$qty} — " . $money(($item['price'] ?? 0) * $qty);
            $size = CustomOrder::sizeLabel($item['options'] ?? null);
            if ($size) $line .= "\n    {$size}";
            $lines[] = $line;
        }

        $lines[] = '';
        // Phase D2: the delivery is part of the total, or still to be quoted.
        if ($delivery = $order->deliverySummary()) {
            $lines[] = "Delivery ({$delivery['name']}): " . $money($delivery['fee']);
        } elseif ($order->needsDeliveryQuote()) {
            $lines[] = 'Delivery: please quote it for my address';
        }
        $lines[] = '*Total: ' . $money($order->total()) . '*' . ($order->needsDeliveryQuote() ? ' + delivery' : '');
        $lines[] = '';
        $lines[] = $order->fulfilment_method === 'pickup' ? '*Collect from shop*' : '*Deliver to*';
        $lines[] = "{$order->full_name} · {$order->phone}";
        $lines[] = $order->fulfilment_method === 'pickup'
            ? trim(($order->fulfilment_snapshot['name'] ?? 'Shop') . ' · ' . ($order->fulfilment_snapshot['address'] ?? ''))
            : trim("{$order->address}, {$order->city}", ', ');
        if ($order->fulfilment_method === 'pickup') $lines[] = $order->handoverDetails()['next'];
        $lines[] = '';
        $lines[] = 'Please confirm and share how to pay. Thank you.';
        $lines[] = 'Track: ' . route('track', ['ref' => $order->cipher]);

        return implode("\n", $lines);
    }

    public static function link(CustomOrder $order): string
    {
        return StoreProfile::whatsappLink(self::text($order));
    }

    /**
     * After an M-Pesa payment: the customer posts the paid order to the studio,
     * so both sides hold the same written record and can check it against
     * their M-Pesa statements. Built only from the settled order (never from
     * the browser): the code Safaricom returned, the amount and time the
     * payment was confirmed, the number charged, the items and the delivery.
     * Null until the order is actually paid.
     */
    public static function paidText(CustomOrder $order): ?string
    {
        if (! $order->isPaidUp()) {
            return null;
        }

        $money = fn ($v) => 'KSh ' . number_format((float) $v);
        $paidFrom = $order->paymentAttempts()->where('status', \App\Models\PaymentAttempt::PAID)->oldest()->value('phone') ?: $order->phone;

        $lines = ['Hello ' . StoreProfile::name() . ',', '', "I've paid for my order. Here are the details for your records:", '',
            "*Order code: {$order->cipher}*",
            // Reconciliation confirms a payment without Safaricom's code; the
            // customer has it in their M-Pesa SMS, so ask for it plainly.
            '*M-Pesa code: ' . ($order->mpesa_receipt_number ?: '[add the code from your M-Pesa SMS]') . '*',
            'Amount paid: ' . $money($order->total()),
            'Paid on: ' . $order->paidAtForDisplay('M d, Y g:i A') . ' (Nairobi)',
        ];
        if ($paidFrom) $lines[] = 'Paid from: ' . StoreProfile::display(Phone::normalize($paidFrom));

        $items = [];
        if ($order->isDrop()) {
            $items[] = '1. ' . ($order->items_json['drop'] ?? 'Drop reservation')
                . (! empty($order->items_json['size']) ? "\n    Size {$order->items_json['size']}" : '');
        } elseif ($order->isShop()) {
            foreach (array_values(array_filter($order->items_json ?? [], fn ($i) => is_array($i) && ! empty($i['name']))) as $i => $item) {
                $qty = (int) ($item['quantity'] ?? 1);
                $line = ($i + 1) . ". {$item['name']} ×{$qty} — " . $money(($item['price'] ?? 0) * $qty);
                if ($size = CustomOrder::sizeLabel($item['options'] ?? null)) $line .= "\n    {$size}";
                $items[] = $line;
            }
        }
        if ($items) array_push($lines, '', '*Items*', ...$items);

        if ($order->isShop()) {
            array_push($lines, '', $order->fulfilment_method === 'pickup' ? '*Collect from shop*' : '*Deliver to*', "{$order->full_name} · " . StoreProfile::display(Phone::normalize($order->phone)),
                $order->fulfilment_method === 'pickup' ? implode(' · ', array_filter([$order->fulfilment_snapshot['name'] ?? 'Shop', $order->fulfilment_snapshot['address'] ?? null])) : trim("{$order->address}, {$order->city}", ', '));
            if ($order->fulfilment_method === 'pickup') $lines[] = $order->handoverDetails()['next'];
        }

        array_push($lines, '', 'Please confirm you have received this payment and let me know when my order is ready. Thank you.',
            'Track: ' . route('track', ['ref' => $order->cipher]));

        return implode("\n", $lines);
    }

    public static function paidLink(CustomOrder $order): ?string
    {
        $text = self::paidText($order);

        return $text === null ? null : StoreProfile::whatsappLink($text);
    }
}
