@extends('treasury.reports.pdf.layout')

@section('subtitle')
    Période du <strong>{{ $day($dateFrom) }}</strong> au <strong>{{ $day($dateTo) }}</strong><br>
    {{ $statement['account']->name }} · {{ $statement['account']->type->label() }} · {{ $statement['account']->currency }}
@endsection

@section('content')
    @php $account = $statement['account']; @endphp

    <table class="summary" style="width: 75%; margin: 0 auto 10px">
        <tr><td>Solde au début de la période ({{ $day($dateFrom) }})</td><td class="num">{{ $money($statement['opening']) }} {{ $account->currency }}</td></tr>
        <tr><td>Total des entrées ({{ $statement['lines']->filter(fn ($line) => $line['receipt'] > 0)->count() }} opération(s))</td><td class="num">{{ $money($statement['receipts']) }} {{ $account->currency }}</td></tr>
        <tr><td>Total des sorties ({{ $statement['lines']->filter(fn ($line) => $line['payment'] > 0)->count() }} opération(s))</td><td class="num">{{ $money($statement['payments']) }} {{ $account->currency }}</td></tr>
        <tr class="result"><td>Solde au terme de la période ({{ $day($dateTo) }})</td><td class="num">{{ $money($statement['closing']) }} {{ $account->currency }}</td></tr>
    </table>

    <h2>Détail des mouvements — {{ $statement['lines']->count() }} opération(s)</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 12%">Date</th>
                <th style="width: 38%">Détail et référence</th>
                <th style="width: 12%">Nature</th>
                <th class="num" style="width: 13%">Entrée ({{ $account->currency }})</th>
                <th class="num" style="width: 13%">Sortie ({{ $account->currency }})</th>
                <th class="num" style="width: 12%">Solde</th>
            </tr>
        </thead>
        <tbody>
            <tr class="carry">
                <td>{{ $day($dateFrom) }}</td>
                <td colspan="4">Solde reporté au début de la période</td>
                <td class="num">{{ $money($statement['opening']) }}</td>
            </tr>
            @forelse ($statement['lines'] as $line)
                @php $transaction = $line['transaction']; @endphp
                <tr>
                    <td>{{ $transaction->transaction_date->format('d/m/Y') }}</td>
                    <td>
                        <strong>{{ $transaction->description }}</strong><br>
                        <span class="muted">Pièce {{ $transaction->number }}@if ($transaction->reference) · Réf. {{ $transaction->reference }}@endif</span>
                        @if ($line['counterpart'])<br><span class="muted">{{ $line['counterpart'] }}</span>@endif
                    </td>
                    <td>{{ $line['nature']->label() }}</td>
                    <td class="num">{{ $line['receipt'] > 0 ? $money($line['receipt']) : '—' }}</td>
                    <td class="num">{{ $line['payment'] > 0 ? $money($line['payment']) : '—' }}</td>
                    <td class="num @if ($line['balance'] < 0) neg @endif">{{ $money($line['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="center muted">Aucun mouvement pour ce compte pendant cette période.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="subtotal">
                <td colspan="3">Totaux de la période</td>
                <td class="num">{{ $money($statement['receipts']) }}</td>
                <td class="num">{{ $money($statement['payments']) }}</td>
                <td></td>
            </tr>
            <tr class="total">
                <td colspan="5">Solde de clôture au {{ $day($dateTo) }}</td>
                <td class="num">{{ $money($statement['closing']) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="box small">
        Lecture du rapport : une entrée augmente le solde du compte ; une sortie le diminue.
        La formule de contrôle est : solde de début + total des entrées − total des sorties = solde de fin.
        Les virements internes apparaissent comme une entrée ou une sortie selon le compte sélectionné.
    </div>

    @include('treasury.reports.pdf._signatures', ['signatories' => [
        ['Établi par', $printedBy?->name],
        ['Vérifié par', null],
        ['Approuvé par', $company->representative_name],
    ]])
@endsection
