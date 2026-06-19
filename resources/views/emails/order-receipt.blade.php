@php
    $currency = \App\Models\ThemeSetting::get('currency', 'KSh');
    $isStandard = $order->type === 'standard';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Serana Receipt</title>
</head>
<body style="margin:0; padding:0; background:#0b0b0c; font-family: Arial, Helvetica, sans-serif; color:#e8e8e8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#141416; border:1px solid #232327; border-radius:20px; overflow:hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="padding:36px 36px 8px;">
                            <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#57d5fc;">Serana Atelier</div>
                            <h1 style="margin:14px 0 6px; font-size:26px; font-weight:300; color:#ffffff;">Payment confirmed.</h1>
                            <p style="margin:0; font-size:14px; color:#9a9aa0; line-height:1.6;">
                                Thank you, {{ $order->full_name }}. We've received your payment and your order is in our hands.
                            </p>
                        </td>
                    </tr>

                    <!-- Reference card -->
                    <tr>
                        <td style="padding:24px 36px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; border:1px solid #232327; border-radius:14px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <div style="font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#9a9aa0;">Order reference</div>
                                        <div style="font-size:20px; font-family:'Courier New', monospace; letter-spacing:1px; color:#ffffff; margin-top:4px;">{{ $order->cipher }}</div>
                                    </td>
                                    @if($order->price_quoted)
                                    <td style="padding:18px 20px; text-align:right;">
                                        <div style="font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#9a9aa0;">Total paid</div>
                                        <div style="font-size:20px; color:#4fb33f; margin-top:4px;">{{ $currency }} {{ number_format((float) $order->price_quoted) }}</div>
                                    </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Items -->
                    @if($isStandard && count($items))
                    <tr>
                        <td style="padding:16px 36px 0;">
                            <div style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#57d5fc; margin-bottom:10px;">Your pieces</div>
                            @foreach($items as $item)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
                                    <tr>
                                        <td style="font-size:14px; color:#e8e8e8;">{{ $item['name'] ?? 'Item' }}</td>
                                        <td style="font-size:13px; color:#9a9aa0; text-align:right;">Qty {{ $item['quantity'] ?? 1 }}</td>
                                    </tr>
                                </table>
                            @endforeach
                        </td>
                    </tr>
                    @endif

                    <!-- M-Pesa -->
                    @if($order->mpesa_receipt_number)
                    <tr>
                        <td style="padding:18px 36px 0;">
                            <div style="font-size:12px; color:#9a9aa0;">M-Pesa transaction: <span style="color:#ffffff; font-weight:bold;">{{ $order->mpesa_receipt_number }}</span></div>
                        </td>
                    </tr>
                    @endif

                    <!-- CTA -->
                    <tr>
                        <td style="padding:28px 36px;">
                            <a href="{{ $trackUrl }}" style="display:inline-block; background:#57d5fc; color:#0b0b0c; text-decoration:none; font-weight:bold; font-size:12px; letter-spacing:1px; padding:15px 28px; border-radius:12px;">
                                Track your order
                            </a>
                            <p style="margin:16px 0 0; font-size:12px; color:#9a9aa0; line-height:1.6;">
                                Track anytime with your reference and the phone number you ordered with.
                                Your full receipt is attached as a PDF.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:20px 36px 32px; border-top:1px solid #232327;">
                            <p style="margin:0; font-size:11px; color:#6c6c72; line-height:1.6;">
                                Serana Atelier &middot; Nairobi, Kenya<br>
                                This receipt was issued on {{ $date }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
