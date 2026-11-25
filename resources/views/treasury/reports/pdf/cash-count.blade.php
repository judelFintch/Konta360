@extends('treasury.reports.pdf.layout')

@section('subtitle')
    {{ $account->name }} · Compte {{ $account->type->accountingCode() }} · {{ $account->currency }}<br>
    Arrêté au <strong>{{ $date->format('d/m/Y') }}</strong> à ____ h ____
@endsection

@section('content')
    <p>
        Nous soussignés certifions avoir procédé contradictoirement, ce jour, au comptage des fonds détenus dans la caisse
        <strong>{{ $account->name }}</strong> et à leur comparaison avec le solde du journal de caisse.
    </p>

    <h2>1. Solde théorique selon le journal de caisse</h2>
    <table class="summary" style="width: 70%">
        <tr><td>Solde comptable au {{ $date->format('d/m/Y') }} (mouvements du jour inclus)</td><td class="num strong">{{ $money($bookBalance) }} {{ $account->currency }}</td></tr>
        <tr><td>Dernière pièce enregistrée</td><td class="num">@if ($lastMovement){{ $lastMovement->number }} du {{ $lastMovement->transaction_date->format('d/m/Y') }}@else — @endif</td></tr>
    </table>

    <h2>2. Billetage (comptage physique des espèces)</h2>
    <table class="grid">
        <thead><tr><th style="width: 30%">Coupure ({{ $account->currency }})</th><th class="num" style="width: 25%">Nombre</th><th class="num">Montant</th></tr></thead>
        <tbody>
            @foreach ($denominations as $value)
                <tr class="blank"><td>{{ $account->currency === 'USD' ? 'Billet' : 'Billet / coupure' }} de {{ number_format($value, 0, ',', ' ') }}</td><td></td><td></td></tr>
            @endforeach
            <tr class="blank"><td>Pièces et appoint</td><td></td><td></td></tr>
            <tr class="subtotal blank"><td colspan="2">Total des espèces comptées (1)</td><td></td></tr>
            <tr class="blank"><td>Chèques et autres valeurs en caisse</td><td></td><td></td></tr>
            <tr class="blank"><td>Pièces justificatives non encore enregistrées (bons, reçus)</td><td></td><td></td></tr>
            <tr class="subtotal blank"><td colspan="2">Total général constaté (2)</td><td></td></tr>
        </tbody>
    </table>

    <h2>3. Rapprochement et écart</h2>
    <table class="grid">
        <tbody>
            <tr class="blank"><td style="width: 60%">Total général constaté (2)</td><td></td></tr>
            <tr><td>Solde théorique selon le journal de caisse</td><td class="num strong">{{ $money($bookBalance) }}</td></tr>
            <tr class="subtotal blank"><td>Écart = (2) − solde théorique</td><td></td></tr>
            <tr><td colspan="2">☐ Aucun écart &nbsp;&nbsp;&nbsp; ☐ Excédent de caisse (produit divers, 758) &nbsp;&nbsp;&nbsp; ☐ Manquant de caisse (charge diverse, 658)</td></tr>
        </tbody>
    </table>

    <h3>Observations et explication de l’écart</h3>
    <table class="grid"><tbody>
        <tr class="blank"><td></td></tr><tr class="blank"><td></td></tr><tr class="blank"><td></td></tr>
    </tbody></table>

    @include('treasury.reports.pdf._signatures', ['signatories' => [
        ['Le caissier', null],
        ['Le contrôleur / comptable', null],
        ['La direction', $company->representative_name],
    ]])
@endsection
