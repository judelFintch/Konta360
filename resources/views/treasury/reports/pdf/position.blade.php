@extends('treasury.reports.pdf.layout')

@section('subtitle')
    Période du <strong>{{ $day($dateFrom) }}</strong> au <strong>{{ $day($dateTo) }}</strong><br>
    Soldes par compte, présentés par devise
@endsection

@section('content')
    @forelse ($groups as $currency => $group)
        <h2>Trésorerie en {{ $currency }}</h2>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 6%">Compte</th>
                    <th>Intitulé</th>
                    <th style="width: 10%">Type</th>
                    <th class="num" style="width: 12%">Solde au {{ $day($dateFrom) }}</th>
                    <th class="num" style="width: 12%">Encaissements</th>
                    <th class="num" style="width: 12%">Décaissements</th>
                    <th class="num" style="width: 12%">Solde au {{ $day($dateTo) }}</th>
                    <th style="width: 17%">Observations</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['rows']->groupBy(fn ($row) => $row['account']->type->value) as $type => $rows)
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['account']->type->accountingCode() }}</td>
                            <td class="strong">{{ $row['account']->name }}</td>
                            <td>{{ $row['account']->type->label() }}</td>
                            <td class="num @if ($row['opening'] < 0) neg @endif">{{ $money($row['opening']) }}</td>
                            <td class="num">{{ $money($row['receipts']) }}</td>
                            <td class="num">{{ $money($row['payments']) }}</td>
                            <td class="num strong @if ($row['closing'] < 0) neg @endif">{{ $money($row['closing']) }}</td>
                            <td class="small @if ($row['closing'] < 0) neg @endif">{{ $row['observation'] }}</td>
                        </tr>
                    @endforeach
                    @php $subtotal = $group['subtotals'][$type]; @endphp
                    <tr class="subtotal">
                        <td colspan="3">Sous-total {{ $subtotal['type'] === \App\Modules\Treasury\Enums\TreasuryAccountType::Bank ? 'banques' : 'caisses' }}</td>
                        <td class="num">{{ $money($subtotal['opening']) }}</td>
                        <td class="num">{{ $money($subtotal['receipts']) }}</td>
                        <td class="num">{{ $money($subtotal['payments']) }}</td>
                        <td class="num">{{ $money($subtotal['closing']) }}</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total">
                    <td colspan="3">Trésorerie totale en {{ $currency }}</td>
                    <td class="num">{{ $money($group['total']['opening']) }}</td>
                    <td class="num">{{ $money($group['total']['receipts']) }}</td>
                    <td class="num">{{ $money($group['total']['payments']) }}</td>
                    <td class="num">{{ $money($group['total']['closing']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <p class="small muted">
            Variation nette de la période : <strong>{{ $money($group['total']['closing'] - $group['total']['opening']) }} {{ $currency }}</strong>.
            Les encaissements et décaissements incluent les virements internes entre comptes de la société.
        </p>
    @empty
        <p class="muted">Aucun compte de trésorerie.</p>
    @endforelse

    <div class="box small">
        Les montants libellés dans des devises différentes ne sont pas additionnés : aucun taux de conversion n’est appliqué.
        Comptes de rattachement (plan SYSCOHADA) : 512 « Banques », 571 « Caisse ».
    </div>

    @include('treasury.reports.pdf._signatures', ['signatories' => [
        ['Établi par (trésorier)', $printedBy?->name],
        ['Vérifié par (comptable)', null],
        ['Approuvé par (direction)', $company->representative_name],
    ]])
@endsection
