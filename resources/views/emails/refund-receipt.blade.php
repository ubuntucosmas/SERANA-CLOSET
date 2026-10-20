@php
    $currency = \App\Models\ThemeSetting::get('currency', 'KSh');
    $money = fn ($v) => $currency . ' ' . number_format((float) $v);
    $contactEmail   = \App\Support\StoreProfile::email();
    $contactAddress = \App\Support\StoreProfile::address();
    $storeName      = \App\Support\StoreProfile::name();
    $storeWhatsApp  = \App\Support\StoreProfile::whatsapp();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your {{ $storeName }} refund</title>
</head>
<body style="margin:0; padding:0; background:#0b0b0c; font-family: Arial, Helvetica, sans-serif; color:#e8e8e8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#141416; border:1px solid #232327; border-radius:20px; overflow:hidden;">
                    <tr>
                        <td style="padding:36px 36px 8px;">
                            <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#57d5fc;">{{ $storeName }}</div>
                            <h1 style="margin:14px 0 6px; font-size:26px; font-weight:300; color:#ffffff;">Refund sent.</h1>
                            <p style="margin:0; font-size:14px; color:#9a9aa0; line-height:1.6;">
                                {{ $order->full_name }}, we've sent {{ $money($refund->amount) }} back to you for order {{ $order->cipher }}.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 36px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; border:1px solid #232327; border-radius:14px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <div style="font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#9a9aa0;">Order reference</div>
                                        <div style="font-size:20px; font-family:'Courier New', monospace; letter-spacing:1px; color:#ffffff; margin-top:4px;">{{ $order->cipher }}</div>
                                    </td>
                                    <td style="padding:18px 20px; text-align:right;">
                                        <div style="font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#9a9aa0;">This refund</div>
                                        <div style="font-size:20px; color:#57d5fc; margin-top:4px;">{{ $money($refund->amount) }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 36px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#e8e8e8;">
                                <tr><td style="padding:4px 0; color:#9a9aa0;">Order total</td><td style="padding:4px 0; text-align:right;">{{ $money($total) }}</td></tr>
                                <tr><td style="padding:4px 0; color:#9a9aa0;">You paid</td><td style="padding:4px 0; text-align:right;">{{ $money($received) }}</td></tr>
                                <tr><td style="padding:4px 0; color:#9a9aa0;">Refunded to date</td><td style="padding:4px 0; text-align:right;">{{ $money($refunded) }}</td></tr>
                                @if($stillToRefund > 0)
                                <tr><td style="padding:4px 0; font-weight:bold;">Still to refund</td><td style="padding:4px 0; text-align:right; font-weight:bold; color:#ffb454;">{{ $money($stillToRefund) }}</td></tr>
                                @else
                                <tr><td style="padding:4px 0; font-weight:bold;">Still to refund</td><td style="padding:4px 0; text-align:right; font-weight:bold; color:#4fb33f;">Nothing, fully refunded</td></tr>
                                @endif
                            </table>
                            <div style="font-size:12px; color:#9a9aa0; margin-top:10px;">Sent by {{ $methodLabel }} · reference <span style="color:#ffffff; font-weight:bold;">{{ $refund->external_reference }}</span> · receipt {{ $receiptNumber }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 36px;">
                            <a href="{{ $trackUrl }}" style="display:inline-block; background:#57d5fc; color:#0b0b0c; text-decoration:none; font-weight:bold; font-size:12px; letter-spacing:1px; padding:15px 28px; border-radius:12px;">Track your order</a>
                            <p style="margin:16px 0 0; font-size:12px; color:#9a9aa0; line-height:1.6;">
                                M-Pesa refunds usually show on your phone within minutes. If you can't see it, reply to this email with the reference above.
                                The refund receipt is attached as a PDF.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 36px 32px; border-top:1px solid #232327;">
                            <p style="margin:0; font-size:11px; color:#6c6c72; line-height:1.6;">
                                {{ $storeName }} &middot; {{ $contactAddress }} &middot; <a href="mailto:{{ $contactEmail }}" style="color:#6c6c72;">{{ $contactEmail }}</a>@if($storeWhatsApp) &middot; <a href="https://wa.me/{{ $storeWhatsApp }}" style="color:#6c6c72;">WhatsApp {{ \App\Support\StoreProfile::display($storeWhatsApp) }}</a>@endif<br>
                                This refund receipt was issued on {{ $date }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
