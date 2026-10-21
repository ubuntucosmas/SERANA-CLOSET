@php $money = fn ($v) => 'KSh ' . number_format((float) $v); @endphp
<!DOCTYPE html>
<html lang="en">
<body style="margin:0; padding:0; background:#0b0b0c; font-family:Helvetica, Arial, sans-serif; color:#e6e6e8;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; padding:24px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px; background:#141416; border-radius:18px;">
    <tr><td style="padding:32px 36px 8px;">
        <div style="font-size:12px; letter-spacing:2px; color:#57d5fc; text-transform:uppercase;">{{ \App\Support\StoreProfile::name() }}</div>
        <h1 style="margin:14px 0 6px; font-size:24px; color:#ffffff;">We’ve received your order</h1>
        <p style="margin:0; font-size:14px; color:#9a9aa0;">Hi {{ $order->full_name }}, your order is reserved for {{ $holdHours }} hours.</p>
    </td></tr>
    <tr><td style="padding:18px 36px 0;">
        <div style="background:#0b0b0c; border-radius:12px; padding:14px 18px;">
            <div style="font-size:11px; color:#9a9aa0; letter-spacing:1px;">ORDER CODE</div>
            <div style="font-size:18px; font-weight:bold; color:#ffffff; letter-spacing:1px;">{{ $order->cipher }}</div>
        </div>
    </td></tr>
    <tr><td style="padding:18px 36px 0;">
        @if($handover = $order->handoverDetails())
<div style="font-size:14px; color:#e6e6e8;">{{ $handover['label'] }} · {{ $handover['location'] }}<br>{{ $handover['address'] }}<br>{{ $handover['next'] }}</div>
@endif
@foreach($items as $item)
            <div style="font-size:14px; color:#e6e6e8; padding:6px 0; border-bottom:1px solid #222;">
                {{ $item['name'] }} × {{ $item['quantity'] ?? 1 }} — {{ $money(($item['price'] ?? 0) * ($item['quantity'] ?? 1)) }}
                @if($size = \App\Models\CustomOrder::sizeLabel($item['options'] ?? null))<br><span style="font-size:12px; color:#9a9aa0;">{{ $size }}</span>@endif
            </div>
        @endforeach
        <div style="font-size:15px; font-weight:bold; color:#ffffff; padding-top:10px;">Total {{ $money($order->price_quoted) }} <span style="font-weight:normal; color:#9a9aa0; font-size:12px;">{{ $order->fulfilment_method === 'pickup' ? '· collection at shop' : '· delivery included' }}</span></div>
    </td></tr>
    <tr><td style="padding:22px 36px 0;">
        <div style="font-size:13px; font-weight:bold; color:#ffffff;">How to pay</div>
        <div style="font-size:14px; color:#e6e6e8; white-space:pre-line; margin-top:6px;">{{ $instructions ?: 'The studio will reply on WhatsApp with how to pay.' }}</div>
        <div style="font-size:12px; color:#9a9aa0; margin-top:6px;">Use {{ $order->cipher }} as the payment reference. You’ll get your receipt as soon as payment is confirmed.</div>
    </td></tr>
    <tr><td style="padding:24px 36px 32px;">
        <a href="{{ $whatsappUrl }}" style="display:inline-block; background:#25D366; color:#06281c; text-decoration:none; font-weight:bold; font-size:13px; padding:14px 22px; border-radius:12px;">Send order on WhatsApp</a>
        &nbsp;
        <a href="{{ $trackUrl }}" style="display:inline-block; color:#57d5fc; font-size:13px; padding:14px 6px;">Track your order</a>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
