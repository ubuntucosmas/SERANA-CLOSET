@php
    // Admin-managed store details, with graceful fallbacks. Same sources the
    // storefront uses, so the receipt never shows stale hardcoded contacts.
    $currency       = \App\Models\ThemeSetting::get('currency', 'KSh');
    $contactEmail   = \App\Models\ThemeSetting::get('contact_email', 'studio@seranacloset.com');
    $contactAddress = \App\Models\ThemeSetting::get('contact_address', 'Nairobi, Kenya');
    $storePhone     = \App\Support\Phone::normalize(
        \App\Models\ThemeSetting::get('whatsapp_number', config('services.whatsapp.number'))
    );
    $trackUrl       = rtrim(config('app.url'), '/') . '/track';
    $isBespoke      = $order->type !== 'standard';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Serana Receipt — {{ $order->cipher ?? ('Order #' . $order->id) }}</title>
    <style>
        /* Print-friendly: white paper, dark ink, the brand cyan used sparingly. */
        @page { margin: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            background: #ffffff;
            color: #1c1c1e;
            margin: 0;
            padding: 48px 52px;
            font-size: 13px;
            line-height: 1.5;
        }
        .header { border-bottom: 2px solid #1c1c1e; padding-bottom: 18px; margin-bottom: 28px; }
        .brand {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 6px;
            text-transform: uppercase;
            color: #1c1c1e;
        }
        .doc-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #0b7da3;
            margin-top: 6px;
            font-weight: bold;
        }
        .grid { width: 100%; margin-bottom: 30px; }
        .col { vertical-align: top; width: 50%; }
        .label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #0b7da3;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .value { font-size: 13px; color: #1c1c1e; }
        .muted { color: #6b6b70; }
        .items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 1px solid #1c1c1e;
            padding: 8px 0;
            color: #1c1c1e;
        }
        .items td { padding: 11px 0; border-bottom: 1px solid #e6e6e8; vertical-align: top; }
        .num { text-align: right; }
        .qty { text-align: center; }
        .total-row { margin-top: 22px; text-align: right; }
        .total-amount { font-size: 24px; font-weight: bold; color: #1c1c1e; margin-top: 4px; }
        .mpesa {
            background: #f2f9f2;
            border: 1px solid #cfe8cf;
            padding: 14px 16px;
            margin-top: 34px;
            border-radius: 4px;
        }
        .footer {
            margin-top: 52px;
            border-top: 1px solid #e6e6e8;
            padding-top: 14px;
            font-size: 9.5px;
            color: #8a8a90;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Serana Closet</div>
        <div class="doc-title">Payment Receipt</div>
    </div>

    <table class="grid">
        <tr>
            <td class="col">
                <div class="label">Billed To</div>
                <div class="value">{{ $order->full_name }}</div>
                @if($order->email)<div class="value muted">{{ $order->email }}</div>@endif
                @if($order->phone)<div class="value muted">{{ $order->phone }}</div>@endif
            </td>
            <td class="col" style="text-align: right;">
                <div class="label">Receipt Details</div>
                <div class="value">Reference: <strong>{{ $order->cipher ?? ('ORD-' . $order->id) }}</strong></div>
                <div class="value muted">Issued: {{ $date }}</div>
                <div class="value" style="color:#1c7a1c; font-weight:bold;">Status: Paid</div>
            </td>
        </tr>
    </table>

    <div class="label">Order Summary</div>
    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="qty">Qty</th>
                <th class="num">Unit Price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
            <tr>
                <td><strong>{{ $item['name'] ?? 'Item' }}</strong>@if(!empty($item['size_label']))<br><span style="font-size:11px; color:#6b6b72;">{{ $item['size_label'] }}</span>@endif</td>
                <td class="qty">{{ $item['quantity'] ?? 1 }}</td>
                <td class="num">{{ $currency }} {{ number_format((float) ($item['price'] ?? 0), 2) }}</td>
                <td class="num">{{ $currency }} {{ number_format((float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1), 2) }}</td>
            </tr>
            @empty
            <tr>
                <td><strong>{{ $isBespoke ? 'Custom order — ' . ucfirst($order->type) : 'Order #' . $order->id }}</strong></td>
                <td class="qty">1</td>
                <td class="num">{{ $currency }} {{ number_format((float) $order->price_quoted, 2) }}</td>
                <td class="num">{{ $currency }} {{ number_format((float) $order->price_quoted, 2) }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="total-row">
        <div class="label">Total Paid</div>
        <div class="total-amount">{{ $currency }} {{ number_format((float) $order->price_quoted, 2) }}</div>
    </div>

    @if($order->mpesa_receipt_number)
    <div class="mpesa">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="label" style="color:#1c7a1c;">M-Pesa Confirmation Code</div>
                    <div style="font-weight: bold; font-size: 15px; letter-spacing: 1px;">{{ $order->mpesa_receipt_number }}</div>
                </td>
                <td style="text-align: right; vertical-align: bottom;">
                    <div class="value muted" style="font-size: 10px;">Paid via M-Pesa (Safaricom)</div>
                </td>
            </tr>
        </table>
    </div>
    @endif

    <div class="footer">
        @if(!empty($order->cipher))
            Track this order at {{ $trackUrl }} using reference {{ $order->cipher }} and the phone number on the order.<br>
        @endif
        Serana Closet &middot; {{ $contactAddress }}
        @if($storePhone) &middot; WhatsApp +{{ $storePhone }} @endif
        &middot; {{ $contactEmail }}<br>
        This is a computer-generated receipt and requires no signature. &copy; {{ date('Y') }} Serana Closet.
    </div>
</body>
</html>
