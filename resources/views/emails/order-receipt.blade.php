@php
    $currency = \App\Models\ThemeSetting::get('currency', 'KSh');
    $isStandard = $order->isShop();
    $money = fn ($v) => $currency . ' ' . number_format((float) $v);
    // Admin-managed store contacts — same sources as the storefront.
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
    <title>Your {{ $storeName }} Receipt</title>
</head>
<body style="margin:0; padding:0; background:#0b0b0c; font-family: Arial, Helvetica, sans-serif; color:#e8e8e8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#141416; border:1px solid #232327; border-radius:20px; overflow:hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="padding:36px 36px 8px;">
                            <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#57d5fc;">{{ $storeName }}</div>
                            @if($unavailable)
                            <h1 style="margin:14px 0 6px; font-size:26px; font-weight:300; color:#ffffff;">Payment received.</h1>
                            <p style="margin:0; font-size:14px; color:#9a9aa0; line-height:1.6;">
                                Thank you, {{ $order->full_name }}. We've received your payment, but {{ implode(', ', $unavailable) }} {{ count($unavailable) === 1 ? 'is' : 'are' }} no longer available: {{ count($unavailable) === 1 ? 'it' : 'they' }} sold out before your payment reached us, so this order can't be fulfilled as placed.
                                Your money is safe. We've sent you a separate email with your options.
                            </p>
                            @else
                            <h1 style="margin:14px 0 6px; font-size:26px; font-weight:300; color:#ffffff;">Payment confirmed.</h1>
                            <p style="margin:0; font-size:14px; color:#9a9aa0; line-height:1.6;">
                                Thank you, {{ $order->full_name }}. We've received your payment and your order is in our hands.
                            </p>
                            @endif
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
                                    @if($payment)
                                    <td style="padding:18px 20px; text-align:right;">
                                        <div style="font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#9a9aa0;">This payment</div>
                                        <div style="font-size:20px; color:#4fb33f; margin-top:4px;">{{ $money($payment->amount) }}</div>
                                    </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if($handover = $order->handoverDetails())
<tr><td style="padding:16px 36px;"><div style="font-size:14px; color:#e6e6e8;">{{ $handover['label'] }}@if($handover['location']) · {{ $handover['location'] }}@endif<br>@if($handover['address']){{ $handover['address'] }}<br>@endif{{ $handover['next'] }}</div></td></tr>
@endif
<!-- Items -->
                    @if($isStandard && count($items))
                    <tr>
                        <td style="padding:16px 36px 0;">
                            <div style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#57d5fc; margin-bottom:10px;">Your pieces</div>
                            @foreach($items as $item)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
                                    <tr>
                                        <td style="font-size:14px; color:#e8e8e8;">{{ $item['name'] ?? 'Item' }}@if(!empty($item['size_label']))<br><span style="font-size:12px; color:#9a9aa0;">{{ $item['size_label'] }}</span>@endif</td>
                                        <td style="font-size:13px; color:#9a9aa0; text-align:right;">Qty {{ $item['quantity'] ?? 1 }}</td>
                                    </tr>
                                </table>
                            @endforeach
                        </td>
                    </tr>
                    @endif

                    <!-- The money, as of this payment -->
                    <tr>
                        <td style="padding:18px 36px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#e8e8e8;">
                                @if($delivery = $order->deliverySummary())
                                <tr><td style="padding:4px 0; color:#9a9aa0;">Delivery · {{ $delivery['name'] }}</td><td style="padding:4px 0; text-align:right;">{{ $delivery['fee'] > 0 ? $money($delivery['fee']) : 'Free' }}</td></tr>
                                @endif
                                <tr><td style="padding:4px 0; color:#9a9aa0;">Order total</td><td style="padding:4px 0; text-align:right;">{{ $money($total) }}</td></tr>
                                <tr><td style="padding:4px 0; color:#9a9aa0;">Paid to date</td><td style="padding:4px 0; text-align:right;">{{ $money($paidToDate) }}</td></tr>
                                @if($unavailable)
                                <tr><td colspan="2" style="padding:8px 0; color:#ffb454;">Item unavailable: see our separate email for a swap, waiting for the next batch, or a full refund.</td></tr>
                                @elseif($excess > 0)
                                <tr><td colspan="2" style="padding:8px 0; color:#ffb454;">@if($paidTwice)We received this payment twice. @else{{ $money($excess) }} more than the total was received. @endif{{ $money($excess) }} will be refunded to you within {{ $refundDays }} working {{ $refundDays === 1 ? 'day' : 'days' }}.</td></tr>
                                @elseif($paidInFull)
                                <tr><td style="padding:4px 0; font-weight:bold;">Balance due</td><td style="padding:4px 0; text-align:right; color:#4fb33f; font-weight:bold;">Paid in full</td></tr>
                                @else
                                <tr><td style="padding:4px 0; font-weight:bold;">Balance due</td><td style="padding:4px 0; text-align:right; font-weight:bold;">{{ $money($balance) }}</td></tr>
                                @endif
                            </table>
                            @if($payment)
                            <div style="font-size:12px; color:#9a9aa0; margin-top:10px;">{{ $methodLabel }}@if($payment->external_reference) · reference <span style="color:#ffffff; font-weight:bold;">{{ $payment->external_reference }}</span>@endif · receipt {{ $receiptNumber }}</div>
                            @endif
                            @if($depositPolicy)
                            <div style="font-size:12px; color:#9a9aa0; margin-top:8px;">{{ $depositPolicy }}</div>
                            @endif
                        </td>
                    </tr>

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
                                {{ $storeName }} &middot; {{ $contactAddress }} &middot; <a href="mailto:{{ $contactEmail }}" style="color:#6c6c72;">{{ $contactEmail }}</a>@if($storeWhatsApp) &middot; <a href="https://wa.me/{{ $storeWhatsApp }}" style="color:#6c6c72;">WhatsApp {{ \App\Support\StoreProfile::display($storeWhatsApp) }}</a>@endif<br>
                                This receipt was issued on {{ $date }}. Questions? Just reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
