@php $money = fn ($v) => 'KSh ' . number_format((float) $v); @endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Balance due</title></head>
<body style="margin:0; padding:0; background:#0b0b0c; font-family: Arial, Helvetica, sans-serif; color:#e8e8e8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#141416; border:1px solid #232327; border-radius:20px;">
                <tr><td style="padding:36px;">
                    <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#57d5fc;">{{ $storeName }}</div>
                    <h1 style="margin:14px 0 12px; font-size:24px; font-weight:300; color:#ffffff;">{{ $money($balance) }} still to pay.</h1>
                    <p style="font-size:14px; color:#c8c8cc; line-height:1.7; margin:0 0 14px;">
                        Hi {{ strtok((string) $order->full_name, ' ') ?: 'there' }}, we received {{ $money($paid) }} for order
                        <span style="font-family:'Courier New', monospace; color:#ffffff;">{{ $order->cipher }}</span>, which comes to {{ $money($total) }}.
                        Your pieces are held for you for {{ $hours }} hours while you pay the balance of {{ $money($balance) }}.
                    </p>
                    @if($payLink)
                    <p style="margin:22px 0;"><a href="{{ $payLink }}" style="display:inline-block; background:#57d5fc; color:#0b0b0c; text-decoration:none; font-weight:bold; font-size:13px; letter-spacing:1px; padding:15px 28px; border-radius:12px;">Pay {{ $money($balance) }} by M-Pesa</a></p>
                    @endif
                    <p style="font-size:13px; color:#9a9aa0; line-height:1.7; margin:0;">
                        If the balance doesn't arrive within {{ $hours }} hours, the order is cancelled, the pieces go back on sale and we refund the {{ $money($paid) }} you paid.
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
