@php $money = fn ($v) => 'KSh ' . number_format((float) $v); @endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>About your payment</title></head>
<body style="margin:0; padding:0; background:#0b0b0c; font-family: Arial, Helvetica, sans-serif; color:#e8e8e8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#141416; border:1px solid #232327; border-radius:20px;">
                <tr><td style="padding:36px;">
                    <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#57d5fc;">{{ $storeName }}</div>
                    <h1 style="margin:14px 0 12px; font-size:24px; font-weight:300; color:#ffffff;">We have your payment. One item needs a decision.</h1>
                    <p style="font-size:14px; color:#c8c8cc; line-height:1.7; margin:0 0 14px;">
                        Hi {{ strtok((string) $order->full_name, ' ') ?: 'there' }}, we received {{ $money($paid) }} for order
                        <span style="font-family:'Courier New', monospace; color:#ffffff;">{{ $order->cipher }}</span>.
                        @if($why) {{ implode(', ', $items) }}: {{ $why }}. @else It arrived after the hold on your bag ended,
                        and in the meantime {{ implode(', ', $items) }} sold out. @endif
                    </p>
                    <p style="font-size:14px; color:#c8c8cc; line-height:1.7; margin:0 0 8px;">Your money is safe. We'll contact you to agree one of these:</p>
                    <ul style="font-size:14px; color:#c8c8cc; line-height:1.8; margin:0 0 14px; padding-left:20px;">
                        <li>swap to another size or piece that's in stock (any price difference is settled either way),</li>
                        <li>wait for the next batch, or</li>
                        <li>a full refund.</li>
                    </ul>
                    <p style="font-size:14px; color:#c8c8cc; line-height:1.7; margin:0;">
                        If we haven't agreed anything within {{ $days }} days, we'll refund you in full, within {{ $refundDays }} working days after that.
                        @if($storeWhatsApp) You can also reach us on <a href="https://wa.me/{{ $storeWhatsApp }}" style="color:#57d5fc;">WhatsApp</a>.@endif
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
