<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Compte de résultat</title><style>
@page{margin:18mm}body{font-family:DejaVu Sans,sans-serif;color:#18212f;font-size:10px}h1{color:#312e81;margin:0}.meta{color:#6b7280;margin:6px 0 24px}.section{background:#312e81;color:white;padding:9px;font-weight:bold}.row,.total,.result{display:table;width:100%;border-bottom:1px solid #e5e7eb}.row span,.total span,.result span{display:table-cell;padding:9px}.row span:last-child,.total span:last-child,.result span:last-child{text-align:right}.total{background:#f3f4f6;font-weight:bold}.result{background:#111827;color:white;font-size:14px;font-weight:bold}
</style></head><body>
<h1>{{ config('app.name', 'Konta360') }} — Compte de résultat</h1><div class="meta">Du {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') }} · {{ $currency }}</div>
<div class="section">Produits</div>@foreach($revenues as $account)<div class="row"><span>{{ $account->code }} — {{ $account->name }}</span><span>{{ number_format((float)$account->total_credit-(float)$account->total_debit,2,',',' ') }}</span></div>@endforeach
<div class="total"><span>Total produits</span><span>{{ number_format($totalRevenue,2,',',' ') }} {{ $currency }}</span></div>
<div class="section">Charges</div>@foreach($expenses as $account)<div class="row"><span>{{ $account->code }} — {{ $account->name }}</span><span>{{ number_format((float)$account->total_debit-(float)$account->total_credit,2,',',' ') }}</span></div>@endforeach
<div class="total"><span>Total charges</span><span>{{ number_format($totalExpense,2,',',' ') }} {{ $currency }}</span></div>
<div class="result"><span>Résultat net</span><span>{{ number_format($netIncome,2,',',' ') }} {{ $currency }}</span></div>
</body></html>
