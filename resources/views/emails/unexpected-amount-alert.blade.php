{{-- Plain text only (no HTML part), so values are printed unescaped. --}}
Order {!! $order->cipher !!} (#{!! $order->id !!}): KSh {!! number_format($paid) !!} was paid against a KSh {!! number_format($prompted) !!} prompt (M-Pesa reference {!! $reference ?? 'not given' !!}).

The money was recorded as received, so the order's balance already reflects it.
Customers cannot change an M-Pesa prompt's amount, so a difference signals a bug or tampering. Please check the M-Pesa statement and the order.

Customer: {!! $order->full_name !!} ({!! $order->whatsapp_number ?: $order->phone !!})
