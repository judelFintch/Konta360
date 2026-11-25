@php
    use App\Modules\Treasury\Enums\TreasuryTransactionType;

    $isThermal = $format === 'epson';
    $typeLabel = $transaction->type->label();
    $direction = match ($transaction->type) {
        TreasuryTransactionType::Inflow => 'ENTRÉE',
        TreasuryTransactionType::Outflow => 'SORTIE',
        TreasuryTransactionType::Transfer => 'VIREMENT INTERNE',
    };
    $logo = $company->brandAssetPath('logo');
    $signature = $company->brandAssetPath('signature');
    $stamp = $company->brandAssetPath('stamp');
    $logoSize = $company->brandImageSize('logo', 175, 60, balanced: true);
    $signatureSize = $company->brandImageSize('signature', 105, 55);
    $stampSize = $company->brandImageSize('stamp', 105, 55);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Preuve {{ $transaction->number }} — {{ $company->name }}</title>
    <style>
        @page { margin: {{ $isThermal ? '4mm' : '15mm' }}; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: {{ $isThermal ? '9px' : '11px' }}; line-height: 1.45; }
        .receipt { width: 100%; margin: 0 auto; }
        .center { text-align: center; }
        .muted { color: #64748b; }
        .rule { margin: 9px 0; border-top: 1px dashed #94a3b8; }
        .solid-rule { margin: 10px 0; border-top: 2px solid #312e81; }
        .company-logo { display: block; max-width: {{ $isThermal ? '48mm' : '55mm' }}; max-height: 22mm; margin: 0 auto 6px; }
        .company-name { color: #111827; font-size: {{ $isThermal ? '13px' : '20px' }}; font-weight: bold; }
        .company-info { margin-top: 4px; color: #475569; font-size: {{ $isThermal ? '8px' : '9px' }}; }
        .title { margin-top: 12px; color: #312e81; font-size: {{ $isThermal ? '12px' : '18px' }}; font-weight: bold; letter-spacing: .6px; }
        .kind { margin-top: 3px; font-size: {{ $isThermal ? '9px' : '11px' }}; font-weight: bold; letter-spacing: 1px; }
        .number { margin-top: 5px; font-size: {{ $isThermal ? '11px' : '15px' }}; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .details td { padding: 3px 0; vertical-align: top; }
        .details td:first-child { width: 38%; color: #64748b; }
        .amount { margin: 10px 0; padding: {{ $isThermal ? '9px 5px' : '14px' }}; border: 1px solid #c7d2fe; border-radius: 5px; background: #f5f7ff; text-align: center; }
        .amount-label { color: #475569; font-size: 9px; text-transform: uppercase; }
        .amount-value { margin-top: 4px; color: #312e81; font-size: {{ $isThermal ? '18px' : '26px' }}; font-weight: bold; }
        .section-title { margin: 10px 0 4px; color: #312e81; font-weight: bold; text-transform: uppercase; font-size: 8px; letter-spacing: .5px; }
        .description { padding: 6px; border: 1px solid #e2e8f0; border-radius: 3px; overflow-wrap: anywhere; }
        .barcode { margin: 12px auto 3px; text-align: center; }
        .barcode img { width: {{ $isThermal ? '30mm' : '34mm' }}; height: {{ $isThermal ? '30mm' : '34mm' }}; }
        .barcode-number { font-family: DejaVu Sans Mono, monospace; font-size: 8px; letter-spacing: 1px; }
        .signoff { margin-top: 12px; text-align: center; font-size: 8px; }
        .brand-mark { margin-top: 8px; color: #94a3b8; text-align: center; font-size: 7px; }
        .a4-grid { margin-top: 12px; }
        .a4-grid td { width: 50%; padding: 7px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .a4-grid strong { display: block; margin-bottom: 2px; color: #64748b; font-size: 8px; text-transform: uppercase; }
        .signatures { margin-top: 18px; }
        .signatures td { width: 50%; padding: 4px; text-align: center; vertical-align: top; }
        .signatures img { max-width: 30mm; max-height: 20mm; object-fit: contain; }
        .sign-line { margin-top: 27px; border-top: 1px solid #94a3b8; padding-top: 4px; }
    </style>
</head>
<body>
    <main class="receipt">
        @if ($logo && $logoSize)<img class="company-logo" src="{{ $logo }}" alt="Logo {{ $company->name }}" style="width: {{ $logoSize[0] }}px; height: {{ $logoSize[1] }}px">@endif
        <div class="company-name center">{{ $company->name }}</div>
        @if ($company->legal_form || $company->address || $company->phone || $company->email || $company->tax_identifier)
            <div class="company-info center">
                @if ($company->legal_form){{ $company->legal_form }}<br>@endif
                @if ($company->address){{ $company->address }}<br>@endif
                {{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}
                @if ($company->tax_identifier)<br>NIF : {{ $company->tax_identifier }}@endif
            </div>
        @endif

        <div class="rule"></div>
        <div class="title center">PREUVE DE TRÉSORERIE</div>
        <div class="kind center">{{ $direction }} · {{ $typeLabel }}</div>
        <div class="number center">{{ $transaction->number }}</div>

        @if ($isThermal)
            <table class="details" style="margin-top: 8px">
                <tr><td>Date de l’opération</td><td>{{ $transaction->transaction_date->format('d/m/Y') }}</td></tr>
                <tr><td>Compte</td><td>{{ $transaction->account->name }}</td></tr>
                @if ($transaction->destinationAccount)<tr><td>Compte destinataire</td><td>{{ $transaction->destinationAccount->name }}</td></tr>@endif
                <tr><td>Nature</td><td>{{ $nature->label() }}</td></tr>
                @if ($transaction->reference)<tr><td>Référence</td><td>{{ $transaction->reference }}</td></tr>@endif
                @if ($balanceAfter !== null)<tr><td>Solde après l’opération</td><td>{{ $money($balanceAfter) }} {{ $transaction->currency }}</td></tr>@endif
            </table>
            <div class="amount">
                <div class="amount-label">Montant de l’opération</div>
                <div class="amount-value">{{ $money($transaction->amount) }} {{ $transaction->currency }}</div>
            </div>
            <div class="section-title">Motif</div>
            <div class="description">{{ $transaction->description }}</div>
        @else
            <div class="solid-rule"></div>
            <table class="a4-grid">
                <tr>
                    <td><strong>Date de l’opération</strong>{{ $transaction->transaction_date->format('d/m/Y') }}</td>
                    <td><strong>Enregistré le</strong>{{ $transaction->created_at->format('d/m/Y à H:i') }}</td>
                </tr>
                <tr>
                    <td><strong>Type de mouvement</strong>{{ $typeLabel }} — {{ $nature->label() }}</td>
                    <td><strong>Compte concerné</strong>{{ $transaction->account->name }} ({{ $transaction->account->type->label() }})</td>
                </tr>
                @if ($transaction->destinationAccount)
                    <tr><td><strong>Compte de départ</strong>{{ $transaction->account->name }}</td><td><strong>Compte destinataire</strong>{{ $transaction->destinationAccount->name }}</td></tr>
                @endif
                <tr>
                    <td><strong>Référence</strong>{{ $transaction->reference ?: '—' }}</td>
                    <td><strong>Établi par</strong>{{ $transaction->creator?->name ?: '—' }}</td>
                </tr>
                @if ($balanceAfter !== null)
                    <tr><td><strong>Solde après l’opération</strong>{{ $money($balanceAfter) }} {{ $transaction->currency }}</td><td><strong>Code de la pièce</strong>{{ $transaction->number }}</td></tr>
                @endif
            </table>
            <div class="amount">
                <div class="amount-label">Montant de l’opération</div>
                <div class="amount-value">{{ $money($transaction->amount) }} {{ $transaction->currency }}</div>
            </div>
            <div class="section-title">Motif de l’opération</div>
            <div class="description">{{ $transaction->description }}</div>
        @endif

        <div class="rule"></div>
        <div class="muted center" style="font-size: 8px">Document généré le {{ $printedAt->format('d/m/Y à H:i') }} par {{ $transaction->creator?->name ?: 'un utilisateur autorisé' }}.</div>
        <div class="barcode">
            <img src="{{ $qrCode }}" alt="Code-barres bidimensionnel de la pièce {{ $transaction->number }}">
            <div class="barcode-number">{{ $transaction->number }}</div>
            <div class="muted" style="font-size: 7px">Scanner pour ouvrir la preuve A4 (connexion requise)</div>
        </div>

        @if (! $isThermal)
            <table class="signatures">
                <tr>
                    <td>@if ($signature && $signatureSize)<img src="{{ $signature }}" alt="Signature de l’entreprise" style="width: {{ $signatureSize[0] }}px; height: {{ $signatureSize[1] }}px">@endif @if ($stamp && $stampSize)<img src="{{ $stamp }}" alt="Cachet de l’entreprise" style="width: {{ $stampSize[0] }}px; height: {{ $stampSize[1] }}px">@endif<div class="sign-line">Signature / cachet de l’entreprise</div></td>
                    <td><div class="sign-line">Visa du déposant / bénéficiaire</div></td>
                </tr>
            </table>
        @endif
        <div class="brand-mark">Konta360 · Preuve de mouvement de trésorerie</div>
    </main>
</body>
</html>
