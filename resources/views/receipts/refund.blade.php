@php
    $currency       = \App\Models\ThemeSetting::get('currency', 'KSh');
    $money          = fn ($v) => $currency . ' ' . number_format((float) $v);
    $contactEmail   = \App\Support\StoreProfile::email();
    $contactAddress = \App\Support\StoreProfile::address();
    $storePhone     = \App\Support\StoreProfile::phone();
    $storeName      = \App\Support\StoreProfile::name();
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $storeName }} Refund — {{ $receiptNumber }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; background: #ffffff; color: #1c1c1e; margin: 0; padding: 48px 52px; font-size: 13px; line-height: 1.5; }
        .header { border-bottom: 2px solid #1c1c1e; padding-bottom: 18px; margin-bottom: 28px; }
        .brand { font-size: 22px; font-weight: bold; letter-spacing: 6px; text-transform: uppercase; }
        .doc-title { font-size: 10px; text-transform: uppercase; letter-spacing: 3px; color: #0b7da3; margin-top: 6px; font-weight: bold; }
        .grid { width: 100%; margin-bottom: 30px; }
        .col { vertical-align: top; width: 50%; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 2px; color: #0b7da3; margin-bottom: 4px; font-weight: bold; }
        .muted { color: #6b6b70; }
        .figures { width: 100%; border-collapse: collapse; }
        .figures td { padding: 9px 0; border-bottom: 1px solid #e6e6e8; }
        .num { text-align: right; }
        .strong td { font-weight: bold; border-bottom: 2px solid #1c1c1e; }
        .box { background: #eef7fb; border: 1px solid #c8e6f2; padding: 14px 16px; margin-top: 30px; border-radius: 4px; }
        .footer { margin-top: 44px; font-size: 10px; color: #6b6b70; border-top: 1px solid #e6e6e8; padding-top: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $storeName }}</div>
        <div class="doc-title">Refund receipt</div>
    </div>

    <table class="grid">
        <tr>
            <td class="col">
                <div class="label">Refunded to</div>
                <div>{{ $order->full_name }}</div>
                <div class="muted">{{ $order->whatsapp_number ?: $order->phone }}</div>
                @if($order->email)<div class="muted">{{ $order->email }}</div>@endif
            </td>
            <td class="col" style="text-align:right;">
                <div class="label">Refund receipt</div>
                <div>{{ $receiptNumber }}</div>
                <div class="muted">Order {{ $order->cipher }}</div>
                <div class="muted">{{ $date }}</div>
            </td>
        </tr>
    </table>

    <table class="figures">
        <tr class="strong"><td>This refund</td><td class="num">{{ $money($refund->amount) }}</td></tr>
        <tr><td>Order total</td><td class="num">{{ $money($total) }}</td></tr>
        <tr><td>Paid by you</td><td class="num">{{ $money($received) }}</td></tr>
        <tr><td>Refunded to date</td><td class="num">{{ $money($refunded) }}</td></tr>
        <tr class="strong"><td>Still to refund</td><td class="num">{{ $stillToRefund > 0 ? $money($stillToRefund) : 'Nothing, fully refunded' }}</td></tr>
    </table>

    <div class="box">
        <div class="label">How it was sent</div>
        <div>{{ $methodLabel }} · reference <strong>{{ $refund->external_reference }}</strong></div>
    </div>

    <div class="footer">
        {{ $storeName }} · {{ $contactAddress }} · {{ $contactEmail }}@if($storePhone) · {{ $storePhone }}@endif
    </div>
</body>
</html>
