@php $money = fn ($v) => ($v < 0 ? '-' : '') . 'KSh ' . number_format(abs((float) $v)); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->cipher }}</title>
    <style>
        /* 80mm thermal paper: about 72mm printable. Black on white, no colour. */
        @page { size: 80mm auto; margin: 4mm; }
        body { font: 12px/1.45 'Courier New', monospace; color: #000; background: #fff; margin: 0 auto; max-width: 72mm; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 2px; letter-spacing: 2px; }
        .c { text-align: center; } .r { text-align: right; } .muted { color: #333; }
        table { width: 100%; border-collapse: collapse; } td { vertical-align: top; padding: 1px 0; }
        .rule { border-top: 1px dashed #000; margin: 6px 0; }
        .total td { font-weight: bold; font-size: 14px; }
        .print { display: block; width: 100%; margin: 12px 0; padding: 10px; font: inherit; }
        @media print { .print { display: none; } }
    </style>
</head>
<body>
    <h1>{{ strtoupper($storeName) }}</h1>
    @if($address)<p class="c muted">{{ $address }}</p>@endif
    <p class="c">{{ $date }}<br>Receipt {{ $order->cipher }}</p>
    <div class="rule"></div>
    <table>
        @foreach($lines as $line)
            <tr>
                <td>{{ $line->line_type === 'discount' ? 'Discount' : ($line->quantity > 1 ? $line->quantity . ' × ' : '') . $line->description }}</td>
                <td class="r">{{ $money($line->amount) }}</td>
            </tr>
        @endforeach
    </table>
    <div class="rule"></div>
    <table>
        <tr class="total"><td>Total</td><td class="r">{{ $money($order->chargeable_total) }}</td></tr>
        @foreach($payments as $row)
            @php $p = $row['payment']; @endphp
            <tr>
                <td>{{ $p->type === 'refund' ? 'Refund · ' : '' }}{{ ['cash' => 'Cash', 'mpesa_manual' => 'M-Pesa', 'mpesa_stk' => 'M-Pesa', 'credit' => 'Credit'][$p->method] ?? ucfirst($p->method) }}@if($p->external_reference) {{ $p->external_reference }}@endif</td>
                <td class="r">{{ $money($p->type === 'refund' ? -$p->amount : $p->amount) }}</td>
            </tr>
            @if($row['tendered'])
                <tr class="muted"><td>Tendered {{ $money($row['tendered']) }}</td><td class="r">Change {{ $money($row['tendered'] - $p->amount) }}</td></tr>
            @endif
        @endforeach
        @if($order->balance_due > 0)<tr><td>Still to pay</td><td class="r">{{ $money($order->balance_due) }}</td></tr>@endif
    </table>
    <div class="rule"></div>
    @if($seller)<p class="c">Served by {{ $seller }}</p>@endif
    @if($footer)<p class="c">{{ $footer }}</p>@endif
    <p class="c muted">Thank you.</p>
    <button class="print" onclick="window.print()">Print</button>
</body>
</html>
