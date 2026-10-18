<!DOCTYPE html>
<html lang="en">
<body style="margin:0; padding:0; background:#0b0b0c; font-family:Helvetica, Arial, sans-serif; color:#e6e6e8;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c; padding:24px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px; background:#141416; border-radius:18px;">
    <tr><td style="padding:32px 36px 8px;">
        <div style="font-size:12px; letter-spacing:2px; color:#57d5fc; text-transform:uppercase;">{{ $storeName }} · Studio</div>
        <h1 style="margin:14px 0 6px; font-size:22px; color:#ffffff;">New custom order{{ $order->priority_pass ? ' (priority)' : '' }}</h1>
        <p style="margin:0; font-size:14px; color:#9a9aa0;">{{ $order->full_name }} · {{ $customerDisplay }} · {{ $order->cipher }}</p>
    </td></tr>
    <tr><td style="padding:18px 36px 0;">
        <div style="background:#0b0b0c; border-radius:12px; padding:14px 18px; font-size:14px; color:#e6e6e8; white-space:pre-line;">{{ $brief }}</div>
        <p style="font-size:12px; color:#9a9aa0; margin:10px 0 0;">The customer was sent to WhatsApp with this brief. They may not have pressed Send, so reply first if nothing has arrived.</p>
    </td></tr>
    <tr><td style="padding:24px 36px 32px;">
        <a href="{{ $studioUrl }}" style="display:inline-block; background:#57d5fc; color:#06202b; text-decoration:none; font-weight:bold; font-size:13px; padding:14px 22px; border-radius:12px;">Open in Studio to quote</a>
        @if($customerWhatsApp)
        &nbsp;<a href="{{ $customerWhatsApp }}" style="display:inline-block; color:#57d5fc; font-size:13px; padding:14px 6px;">Message the customer</a>
        @endif
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
