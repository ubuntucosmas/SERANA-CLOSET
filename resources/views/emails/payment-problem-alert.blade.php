{{-- Plain text only, so values are printed unescaped. --}}
{!! $body !!}

Customer: {!! $order->full_name !!} ({!! $order->whatsapp_number ?: $order->phone !!})
Open it in the Studio: {!! route('studio.index', ['tab' => 'orders', 'order' => $order->id]) !!}
