@php $money = fn ($v) => 'KSh ' . number_format((float) $v); @endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Ready for pickup</title></head>
<body style="margin:0; padding:0; background:#0b0b0c; font-family: Arial, Helvetica, sans-serif; color:#e8e8e8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#141416; border:1px solid #232327; border-radius:20px;">
                <tr><td style="padding:36px;">
                    <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#57d5fc;">{{ $storeName }}</div>
                    <h1 style="margin:14px 0 12px; font-size:24px; font-weight:300; color:#ffffff;">Your order is ready for pickup.</h1>
                    <p style="font-size:14px; color:#c8c8cc; line-height:1.7; margin:0 0 14px;">
                        Hi {{ strtok((string) $order->full_name, ' ') ?: 'there' }}, order
                        <span style="font-family:'Courier New', monospace; color:#ffffff;">{{ $order->cipher }}</span> is waiting for you at {{ $shop }}@if($address), {{ $address }}@endif.
                    </p>
                    <p style="font-size:14px; color:#c8c8cc; line-height:1.7; margin:0 0 8px;">Bring:</p>
                    <ul style="font-size:14px; color:#c8c8cc; line-height:1.8; margin:0 0 14px; padding-left:20px;">
                        <li>your order reference, <strong style="color:#ffffff;">{{ $order->cipher }}</strong>, and</li>
                        <li>the phone number you ordered with. We check it before we hand the order over.</li>
                    </ul>
                    @if($balance > 0)
                        <p style="font-size:14px; color:#ffffff; line-height:1.7; margin:0 0 14px;">{{ $money($balance) }} is still owed. Settle it at the till when you collect.</p>
                    @endif
                    <p style="font-size:14px; color:#c8c8cc; line-height:1.7; margin:0;">
                        Someone collecting for you needs the same reference and phone number.
                        @if($storeWhatsApp) Questions? <a href="https://wa.me/{{ $storeWhatsApp }}" style="color:#57d5fc;">WhatsApp us</a>.@endif
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
