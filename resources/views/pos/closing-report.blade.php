<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Closing report · {{ $report['register'] }}</title>
<style>body{font:15px/1.5 system-ui,sans-serif;color:#172033;max-width:800px;margin:32px auto;padding:16px}table{width:100%;border-collapse:collapse}th,td{padding:9px;border-bottom:1px solid #ddd;text-align:left}td:last-child{text-align:right;font-variant-numeric:tabular-nums}button{padding:10px 16px}@media print{button{display:none}body{margin:0;max-width:none}}</style></head><body>
@php($money = fn($n) => 'KSh '.number_format((int)$n))
<button onclick="window.print()">Print report</button>
<h1>{{ \App\Support\StoreProfile::name() }} · Closing report (Z)</h1><p>{{ $till->location?->name }} · {{ $report['register'] }} · Session #{{ $till->id }} · {{ ucfirst($till->status) }}</p>
<p>Opened {{ $report['opened_at'] }} by {{ $report['opened_by'] }}<br>Closed {{ $report['closed_at'] ?? 'Not yet closed' }} by {{ $till->closedBy?->name ?? '—' }}</p>
<p>Purpose: reconcile this drawer's cash. Completion: the count, difference and any reason/approval are recorded below. An open session is provisional.</p>
<table><tbody>
@foreach(['opening_float'=>'Opening float','expected_cash'=>'Expected cash','counted_cash'=>'Counted cash','variance'=>'Difference'] as $key=>$label)<tr><th>{{ $label }}</th><td>{{ $report[$key] === null ? '—' : $money($report[$key]) }}</td></tr>@endforeach
@foreach($report['cash'] as $type=>$amount)<tr><th>Cash · {{ ucfirst($type) }}</th><td>{{ $money($amount) }}</td></tr>@endforeach
@foreach($report['sales_by_method'] as $type=>$amount)<tr><th>Received · {{ $type }}</th><td>{{ $money($amount) }}</td></tr>@endforeach
@foreach($report['refunds_by_method'] as $type=>$amount)<tr><th>Refunded · {{ $type }}</th><td>{{ $money($amount) }}</td></tr>@endforeach
</tbody></table><p>{{ $report['sales_count'] }} sales with payment · {{ $report['voids'] }} voids · discounts {{ $money($report['discounts']) }}</p>
<p>Reason for difference: {{ $report['variance_reason'] ?? 'None recorded' }}<br>Approved by {{ $till->acknowledgedBy?->name ?? 'Not required / not recorded' }}</p>
@if(count($report['unfinished']))<h2>Orders still owed</h2>@foreach($report['unfinished'] as $order)<p>{{ $order['reference'] }} · {{ $money($order['balance_due']) }}</p>@endforeach @endif
<p>All times Nairobi. A till report reconciles recorded activity; it is not a bank statement.</p></body></html>
