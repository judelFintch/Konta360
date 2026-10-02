<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Balance générale {{ $currency }}</title>
    <style>
        @page { margin: 14mm; }
        body { color: #18212f; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { margin: 0; color: #312e81; font-size: 21px; }
        .meta { margin: 5px 0 22px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 8px 6px; color: #fff; background: #312e81; font-size: 7px; text-align: left; text-transform: uppercase; }
        td { padding: 8px 6px; border-bottom: 1px solid #e5e7eb; }
        .number { text-align: right; }
        tfoot td { color: #fff; background: #111827; border: 0; font-weight: bold; }
        .footer { margin-top: 20px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ \App\Models\CompanySetting::current()->name }} — Balance générale</h1>
    <div class="meta">Période du {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') }} · Devise : {{ $currency }}</div>
    <table>
        <thead><tr><th>Compte</th><th>Intitulé</th><th class="number">Mouv. débit</th><th class="number">Mouv. crédit</th><th class="number">Solde débiteur</th><th class="number">Solde créditeur</th></tr></thead>
        <tbody>
            @foreach ($accounts as $account)
                @php $debit = (float) $account->total_debit; $credit = (float) $account->total_credit; @endphp
                <tr><td><strong>{{ $account->code }}</strong></td><td>{{ $account->name }}</td><td class="number">{{ number_format($debit, 2, ',', ' ') }}</td><td class="number">{{ number_format($credit, 2, ',', ' ') }}</td><td class="number">{{ number_format(max(0, $debit - $credit), 2, ',', ' ') }}</td><td class="number">{{ number_format(max(0, $credit - $debit), 2, ',', ' ') }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="2">Totaux {{ $currency }}</td><td class="number">{{ number_format($accounts->sum(fn ($a) => (float) $a->total_debit), 2, ',', ' ') }}</td><td class="number">{{ number_format($accounts->sum(fn ($a) => (float) $a->total_credit), 2, ',', ' ') }}</td><td class="number">{{ number_format($accounts->sum(fn ($a) => max(0, (float) $a->total_debit - (float) $a->total_credit)), 2, ',', ' ') }}</td><td class="number">{{ number_format($accounts->sum(fn ($a) => max(0, (float) $a->total_credit - (float) $a->total_debit)), 2, ',', ' ') }}</td></tr></tfoot>
    </table>
    <div class="footer">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</body>
</html>
