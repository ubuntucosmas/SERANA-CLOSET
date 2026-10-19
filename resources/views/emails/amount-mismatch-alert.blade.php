{{-- Plain text only (no HTML part), so values are printed unescaped, as the old Mail::raw did. --}}
Order {!! $order->cipher !!} (#{!! $order->id !!}) received an M-Pesa payment of KSh {!! $paid !!} but KSh {!! $expected !!} was quoted. The order was NOT marked paid.

Customer: {!! $order->full_name !!} ({!! $order->phone !!})
The money has been collected — this needs a manual refund or follow-up.
