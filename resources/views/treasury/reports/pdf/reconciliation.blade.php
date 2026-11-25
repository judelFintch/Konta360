@extends('treasury.reports.pdf.layout')

@section('subtitle')
    N° <strong>{{ $reconciliation->number }}</strong> · {{ $account->name }} (compte 512) · {{ $reconciliation->currency }}<br>
    Relevé du <strong>{{ $reconciliation->starts_on->format('d/m/Y') }}</strong> au <strong>{{ $reconciliation->ends_on->format('d/m/Y') }}</strong>
@endsection

@section('content')
    @php $cur = $reconciliation->currency; @endphp

    <h2>I. Mouvements pointés sur le relevé</h2>
    <table class="grid">
        <thead><tr><th style="width: 10%">Date</th><th style="width: 15%">N° pièce</th><th>Libellé</th><th style="width: 13%">Référence</th><th class="num" style="width: 13%">Encaissements</th><th class="num" style="width: 13%">Décaissements</th></tr></thead>
        <tbody>
            <tr class="carry"><td colspan="4">Solde initial selon relevé bancaire</td><td class="num" colspan="2">{{ $money($reconciliation->statement_opening_balance) }}</td></tr>
            @forelse ($ticked as $item)
                <tr>
                    <td>{{ $item['transaction']->transaction_date->format('d/m/Y') }}</td>
                    <td class="strong">{{ $item['transaction']->number }}</td>
                    <td>{{ $item['transaction']->description }}</td>
                    <td>{{ $item['transaction']->reference }}</td>
                    <td class="num">{{ $item['signed'] > 0 ? $money($item['signed']) : '' }}</td>
                    <td class="num">{{ $item['signed'] < 0 ? $money(-$item['signed']) : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="center muted">Aucun mouvement pointé.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="subtotal"><td colspan="4">Totaux pointés</td><td class="num">{{ $money($tickedReceipts) }}</td><td class="num">{{ $money($tickedPayments) }}</td></tr>
            <tr class="total"><td colspan="4">Solde final selon relevé bancaire</td><td class="num" colspan="2">{{ $money($reconciliation->statement_closing_balance) }} {{ $cur }}</td></tr>
        </tfoot>
    </table>
    <p class="small">
        Contrôle : {{ $money($reconciliation->statement_opening_balance) }} + {{ $money($tickedReceipts) }} − {{ $money($tickedPayments) }}
        = {{ $money($reconciliation->calculated_closing_balance) }} ·
        Écart de pointage : <span class="{{ abs((float) $reconciliation->difference) < 0.01 ? 'ok' : 'warn' }}">{{ $money($reconciliation->difference) }}</span>
    </p>

    <h2>II. État de rapprochement au {{ $reconciliation->ends_on->format('d/m/Y') }}</h2>
    <table class="summary">
        <tr><td class="strong">Solde selon la comptabilité (journal de banque) au {{ $reconciliation->ends_on->format('d/m/Y') }}</td><td class="num strong">{{ $money($bookBalance) }}</td></tr>
        <tr><td>− Encaissements comptabilisés non encore crédités par la banque ({{ $outstandingReceipts->count() }})</td><td class="num">{{ $money($outstandingReceipts->sum('signed')) }}</td></tr>
        <tr><td>+ Décaissements comptabilisés non encore débités par la banque ({{ $outstandingPayments->count() }})</td><td class="num">{{ $money(-$outstandingPayments->sum('signed')) }}</td></tr>
        <tr class="result"><td>= Solde comptable rapproché</td><td class="num">{{ $money($adjustedBook) }}</td></tr>
        <tr><td class="strong">Solde selon le relevé bancaire</td><td class="num strong">{{ $money($reconciliation->statement_closing_balance) }}</td></tr>
        <tr class="result">
            <td>Écart résiduel</td>
            <td class="num {{ abs($residual) < 0.01 ? 'ok' : 'warn' }}">{{ $money($residual) }} {{ $cur }}</td>
        </tr>
    </table>
    @if (abs($residual) >= 0.01)
        <div class="box small warn">
            Un écart résiduel subsiste : il provient généralement d’un solde d’ouverture du compte différent de celui de la banque,
            d’opérations bancaires non encore comptabilisées (frais, agios, virements reçus) ou de mouvements antérieurs non rapprochés.
            Il doit être analysé et régularisé par écriture.
        </div>
    @endif

    @if ($outstandingReceipts->isNotEmpty() || $outstandingPayments->isNotEmpty())
        <h2>III. Détail des opérations en suspens</h2>
        <table class="grid">
            <thead><tr><th style="width: 10%">Date</th><th style="width: 15%">N° pièce</th><th>Libellé</th><th style="width: 13%">Référence</th><th class="num" style="width: 13%">Encaissements</th><th class="num" style="width: 13%">Décaissements</th></tr></thead>
            <tbody>
                @foreach ($outstandingReceipts->concat($outstandingPayments)->sortBy(fn ($item) => $item['transaction']->transaction_date->toDateString().'-'.str_pad($item['transaction']->id, 10, '0', STR_PAD_LEFT)) as $item)
                    <tr>
                        <td>{{ $item['transaction']->transaction_date->format('d/m/Y') }}</td>
                        <td class="strong">{{ $item['transaction']->number }}</td>
                        <td>{{ $item['transaction']->description }}</td>
                        <td>{{ $item['transaction']->reference }}</td>
                        <td class="num">{{ $item['signed'] > 0 ? $money($item['signed']) : '' }}</td>
                        <td class="num">{{ $item['signed'] < 0 ? $money(-$item['signed']) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($reconciliation->notes)
        <div class="box"><span class="strong">Notes :</span> {{ $reconciliation->notes }}</div>
    @endif

    <p class="small muted">Rapprochement validé le {{ $reconciliation->completed_at?->format('d/m/Y à H:i') }} par {{ $reconciliation->creator?->name }}.</p>

    @include('treasury.reports.pdf._signatures', ['signatories' => [
        ['Établi par', $reconciliation->creator?->name],
        ['Vérifié par (comptable)', null],
        ['Approuvé par (direction)', $company->representative_name],
    ]])
@endsection
