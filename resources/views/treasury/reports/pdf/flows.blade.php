@extends('treasury.reports.pdf.layout')

@section('subtitle')
    Période du <strong>{{ $day($dateFrom) }}</strong> au <strong>{{ $day($dateTo) }}</strong><br>
    Méthode directe · Montants en {{ $currency }} · {{ $flows['accountsCount'] }} compte{{ $flows['accountsCount'] > 1 ? 's' : '' }}
@endsection

@section('content')
    <h2>I. Synthèse des flux de trésorerie</h2>
    <table class="grid">
        <thead><tr><th style="width: 8%">Réf.</th><th>Rubrique</th><th class="num" style="width: 10%">Opérations</th><th class="num" style="width: 20%">Montant ({{ $currency }})</th></tr></thead>
        <tbody>
            <tr class="carry"><td>A</td><td>Trésorerie d’ouverture au {{ $day($dateFrom) }}</td><td></td><td class="num">{{ $money($flows['opening']) }}</td></tr>

            <tr class="subtotal"><td>B</td><td colspan="3">Encaissements</td></tr>
            @foreach ($flows['receipts'] as $i => $row)
                <tr><td>B{{ $i + 1 }}</td><td>{{ $row['nature']->label() }}</td><td class="num">{{ $row['count'] }}</td><td class="num">{{ $money($row['amount']) }}</td></tr>
            @endforeach
            <tr class="subtotal"><td></td><td>Total des encaissements (B)</td><td></td><td class="num">{{ $money($flows['totalReceipts']) }}</td></tr>

            <tr class="subtotal"><td>C</td><td colspan="3">Décaissements</td></tr>
            @foreach ($flows['payments'] as $i => $row)
                <tr><td>C{{ $i + 1 }}</td><td>{{ $row['nature']->label() }}</td><td class="num">{{ $row['count'] }}</td><td class="num">{{ $money($row['amount']) }}</td></tr>
            @endforeach
            <tr class="subtotal"><td></td><td>Total des décaissements (C)</td><td></td><td class="num">{{ $money($flows['totalPayments']) }}</td></tr>

            <tr class="subtotal"><td>D</td><td>Flux net de trésorerie de la période (B − C)</td><td></td><td class="num @if ($flows['net'] < 0) neg @endif">{{ $money($flows['net']) }}</td></tr>
        </tbody>
        <tfoot>
            <tr class="total"><td>E</td><td>Trésorerie de clôture au {{ $day($dateTo) }} (A + D)</td><td></td><td class="num">{{ $money($flows['closing']) }}</td></tr>
        </tfoot>
    </table>

    <table class="summary" style="margin-top: 6px">
        <tr>
            <td>Pour mémoire — virements internes entre comptes ({{ $flows['transfers']['count'] }}), sans effet sur la trésorerie globale</td>
            <td class="num">{{ $money($flows['transfers']['amount']) }}</td>
        </tr>
        <tr>
            <td>Contrôle — somme des soldes des comptes en {{ $currency }} au {{ $day($dateTo) }}</td>
            <td class="num">
                {{ $money($flows['closingControl']) }}
                @if (abs($flows['closingControl'] - $flows['closing']) < 0.01)<span class="ok">✓ concordant</span>@else<span class="warn">écart {{ $money($flows['closingControl'] - $flows['closing']) }}</span>@endif
            </td>
        </tr>
    </table>

    <h2>II. Évolution mensuelle</h2>
    <table class="grid">
        <thead><tr><th>Mois</th><th class="num">Encaissements</th><th class="num">Décaissements</th><th class="num">Flux net</th><th class="num">Trésorerie fin de mois</th></tr></thead>
        <tbody>
            @forelse ($flows['monthly'] as $month)
                <tr>
                    <td class="strong">{{ ucfirst($month['month']->locale('fr')->isoFormat('MMMM YYYY')) }}</td>
                    <td class="num">{{ $money($month['receipts']) }}</td>
                    <td class="num">{{ $money($month['payments']) }}</td>
                    <td class="num @if ($month['net'] < 0) neg @endif">{{ $money($month['net']) }}</td>
                    <td class="num strong">{{ $money($month['closing']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="center muted">Aucun mouvement sur la période.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total"><td>Total période</td><td class="num">{{ $money($flows['totalReceipts']) }}</td><td class="num">{{ $money($flows['totalPayments']) }}</td><td class="num">{{ $money($flows['net']) }}</td><td class="num">{{ $money($flows['closing']) }}</td></tr>
        </tfoot>
    </table>

    <div class="box small">
        Présentation selon la méthode directe : les flux sont classés d’après la nature de l’opération de trésorerie
        (règlements clients, paiements fournisseurs, autres opérations). Les virements entre comptes de la société
        sont neutralisés. Aucun taux de change n’est appliqué : ce tableau ne couvre que la devise {{ $currency }}.
    </div>

    @include('treasury.reports.pdf._signatures', ['signatories' => [
        ['Établi par (trésorier)', $printedBy?->name],
        ['Vérifié par (comptable)', null],
        ['Approuvé par (direction)', $company->representative_name],
    ]])
@endsection
