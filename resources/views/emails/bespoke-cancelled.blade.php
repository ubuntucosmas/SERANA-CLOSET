@php
    $money = fn ($v) => 'KSh ' . number_format((float) $v);
    $within = 'within ' . $refundDays . ' working ' . ($refundDays === 1 ? 'day' : 'days');
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Order cancelled</title></head>
<body style="margin:0; padding:0; background:#0b0b0c; font-family: Arial, Helvetica, sans-serif; color:#e8e8e8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#141416; border:1px solid #232327; border-radius:20px;">
                <tr><td style="padding:36px;">
                    <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#57d5fc;">{{ $storeName }}</div>
                    <h1 style="margin:14px 0 12px; font-size:24px; font-weight:300; color:#ffffff;">Your order is cancelled.</h1>
                    <p style="font-size:14px; color:#c8c8cc; line-height:1.7; margin:0 0 14px;">
                        Hi {{ strtok((string) $order->full_name, ' ') ?: 'there' }}, order
                        <span style="font-family:'Courier New', monospace; color:#ffffff;">{{ $order->cipher }}</span> has been cancelled.
                    </p>
                    <p style="font-size:14px; color:#c8c8cc; line-height:1.7; margin:0 0 14px;">
                        @if($retained > 0)
                            Work on your piece had already started, so your deposit of {{ $money($retained) }} is kept, as our deposit policy says.
                            @if($owedBack > 0) The {{ $money($owedBack) }} you paid above the deposit will be refunded to you {{ $within }}.@endif
                        @elseif($owedBack > 0)
                            Work had not started yet, so nothing is kept. Your deposit of {{ $money($owedBack) }} will be refunded to you {{ $within }}.
                        @else
                            Nothing was paid on this order, so there is nothing to refund.
                        @endif
                    </p>
                    @if($retained > 0)
                    <p style="font-size:13px; color:#c8c8cc; line-height:1.7; margin:0 0 10px;">Cancelled at: {{ $stage }}</p>
                    @endif
                    <p style="font-size:12px; color:#9a9aa0; line-height:1.6; margin:0;">Deposit policy: {{ $policy }}</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
