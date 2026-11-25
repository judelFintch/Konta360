@extends('treasury.reports.pdf.layout')

@section('subtitle')
    Période du <strong>{{ $day($dateFrom) }}</strong> au <strong>{{ $day($dateTo) }}</strong><br>
    @if ($account){{ $account->name }} · {{ $account->type->label() }} · Compte {{ $account->type->accountingCode() }} · {{ $account->currency }}@else Tous les comptes de trésorerie @endif
@endsection

@section('content')
    @forelse ($statements as $statement)
        @php $item = $statement['account']; @endphp
        <div @class(['page-break' => ! $loop->first])>
            <h2>{{ $item->type->accountingCode() }} — {{ $item->name }} <span class="muted" style="font-weight: normal">({{ $item->type->label() }}, montants en {{ $item->currency }})</span></h2>
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width: 7%">Date</th>
                        <th style="width: 11%">N° pièce</th>
                        <th>Libellé</th>
                        <th style="width: 15%">Nature / contrepartie</th>
                        <th style="width: 9%">Référence</th>
                        <th class="num" style="width: 10%">Encaissements</th>
                        <th class="num" style="width: 10%">Décaissements</th>
                        <th class="num" style="width: 10%">Solde</th>
                        <th style="width: 7%">Pointage</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="carry">
                        <td>{{ $day($dateFrom) }}</td>
                        <td colspan="6">Solde reporté (à nouveau) au {{ $day($dateFrom) }}</td>
                        <td class="num @if ($statement['opening'] < 0) neg @endif">{{ $money($statement['opening']) }}</td>
                        <td></td>
                    </tr>
                    @forelse ($statement['lines'] as $line)
                        @php $transaction = $line['transaction']; @endphp
                        <tr>
                            <td>{{ $transaction->transaction_date->format('d/m/Y') }}</td>
                            <td class="strong">{{ $transaction->number }}</td>
                            <td>{{ $transaction->description }}</td>
                            <td>{{ $line['nature']->label() }}@if ($line['counterpart'])<br><span class="muted">{{ $line['counterpart'] }}</span>@endif</td>
                            <td>{{ $transaction->reference }}</td>
                            <td class="num">{{ $line['receipt'] > 0 ? $money($line['receipt']) : '' }}</td>
                            <td class="num">{{ $line['payment'] > 0 ? $money($line['payment']) : '' }}</td>
                            <td class="num @if ($line['balance'] < 0) neg @endif">{{ $money($line['balance']) }}</td>
                            <td class="small">{{ $line['reconciliation'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="center muted">Aucun mouvement sur la période.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="subtotal">
                        <td colspan="5">Totaux de la période ({{ $statement['lines']->count() }} mouvement{{ $statement['lines']->count() > 1 ? 's' : '' }})</td>
                        <td class="num">{{ $money($statement['receipts']) }}</td>
                        <td class="num">{{ $money($statement['payments']) }}</td>
                        <td></td><td></td>
                    </tr>
                    <tr class="total">
                        <td colspan="7">Solde de clôture au {{ $day($dateTo) }} ({{ $item->currency }})</td>
                        <td class="num">{{ $money($statement['closing']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <table class="summary" style="width: 50%; margin-top: 8px">
                <tr><td>Solde d’ouverture au {{ $day($dateFrom) }}</td><td class="num">{{ $money($statement['opening']) }}</td></tr>
                <tr><td>+ Total des encaissements</td><td class="num">{{ $money($statement['receipts']) }}</td></tr>
                <tr><td>− Total des décaissements</td><td class="num">{{ $money($statement['payments']) }}</td></tr>
                <tr class="result"><td>= Solde de clôture au {{ $day($dateTo) }}</td><td class="num">{{ $money($statement['closing']) }} {{ $item->currency }}</td></tr>
            </table>
            @if ($item->type === \App\Modules\Treasury\Enums\TreasuryAccountType::Cash && $statement['closing'] < 0)
                <div class="box warn">Attention : la caisse présente un solde créditeur. Une caisse ne peut pas être négative ; l’écart doit être justifié.</div>
            @endif
        </div>
    @empty
        <p class="muted">Aucun compte de trésorerie.</p>
    @endforelse

    @include('treasury.reports.pdf._signatures', ['signatories' => [
        ['Établi par (caissier / trésorier)', $printedBy?->name],
        ['Vérifié par (comptable)', null],
        ['Approuvé par (direction)', $company->representative_name],
    ]])
@endsection
