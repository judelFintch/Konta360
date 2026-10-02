<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Bilan</title><style>
@page{margin:18mm}body{font-family:DejaVu Sans,sans-serif;color:#18212f;font-size:9px}h1{color:#312e81;margin:0}.meta{color:#6b7280;margin:6px 0 24px}.columns{width:100%;border-collapse:separate;border-spacing:10px 0}.columns>tbody>tr>td{width:50%;vertical-align:top;border:1px solid #e5e7eb}.title{background:#312e81;color:white;padding:10px;font-weight:bold}.row,.total{display:table;width:100%;border-bottom:1px solid #e5e7eb}.row span,.total span{display:table-cell;padding:9px}.row span:last-child,.total span:last-child{text-align:right}.total{background:#e0e7ff;color:#1e1b4b;font-weight:bold}
</style></head><body>
<h1>{{ \App\Models\CompanySetting::current()->name }} — Bilan</h1><div class="meta">Situation au {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') }} · {{ $currency }}</div>
<table class="columns"><tr><td><div class="title">Actif</div>
@foreach($assets as $account)<div class="row"><span>{{ $account->code }} — {{ $account->name }}</span><span>{{ number_format((float)$account->total_debit-(float)$account->total_credit,2,',',' ') }}</span></div>@endforeach
<div class="total"><span>Total actif</span><span>{{ number_format($totalAssets,2,',',' ') }} {{ $currency }}</span></div>
</td><td><div class="title">Passif et capitaux propres</div>
@foreach($liabilities as $account)<div class="row"><span>{{ $account->code }} — {{ $account->name }}</span><span>{{ number_format((float)$account->total_credit-(float)$account->total_debit,2,',',' ') }}</span></div>@endforeach
@foreach($equity as $account)<div class="row"><span>{{ $account->code }} — {{ $account->name }}</span><span>{{ number_format((float)$account->total_credit-(float)$account->total_debit,2,',',' ') }}</span></div>@endforeach
<div class="row"><span>Résultat cumulé</span><span>{{ number_format($retainedIncome,2,',',' ') }}</span></div>
<div class="total"><span>Total passif</span><span>{{ number_format($totalLiabilitiesAndEquity,2,',',' ') }} {{ $currency }}</span></div>
</td></tr></table>
</body></html>
