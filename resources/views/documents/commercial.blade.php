@php $company = \App\Models\CompanySetting::current(); @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $documentType }} {{ $documentNumber }}</title>
    <style>
        @page { margin: 20mm 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #18212f; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; }
        .toolbar { display: flex; justify-content: center; gap: 10px; padding: 14px; background: #111827; position: sticky; top: 0; }
        .toolbar a, .toolbar button { border: 0; border-radius: 6px; padding: 9px 14px; cursor: pointer; color: #fff; background: #4f46e5; font: 600 13px sans-serif; text-decoration: none; }
        .toolbar .secondary { background: #374151; }
        .page { max-width: 210mm; min-height: 270mm; margin: 20px auto; padding: 14mm; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,.12); }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 32px; }
        .header td { width: 50%; vertical-align: top; }
        .brand { color: #4f46e5; font-size: 23px; font-weight: bold; }
        .logo { max-width: 150px; max-height: 70px; margin-bottom: 8px; }
        .approval-assets { display: flex; align-items: end; justify-content: flex-end; gap: 18px; margin-top: 28px; }
        .approval-assets img { max-width: 115px; max-height: 75px; object-fit: contain; }
        .document-title { margin: 0; color: #111827; font-size: 25px; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .badge { display: inline-block; margin-top: 6px; padding: 4px 9px; border-radius: 20px; color: #3730a3; background: #e0e7ff; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .parties { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
        .parties td { width: 50%; padding: 14px; vertical-align: top; border: 1px solid #e5e7eb; }
        .parties td:first-child { background: #f9fafb; }
        .label { margin-bottom: 7px; color: #6b7280; font-size: 8px; font-weight: bold; letter-spacing: .08em; text-transform: uppercase; }
        .party-name { margin-bottom: 3px; font-size: 14px; font-weight: bold; }
        .lines { width: 100%; border-collapse: collapse; }
        .lines th { padding: 9px 7px; color: #4b5563; background: #f3f4f6; border-bottom: 1px solid #d1d5db; font-size: 8px; letter-spacing: .04em; text-align: left; text-transform: uppercase; }
        .lines td { padding: 10px 7px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .lines .number { text-align: right; white-space: nowrap; }
        .sku { margin-top: 2px; color: #6b7280; font-size: 9px; }
        .summary-wrap { width: 100%; margin-top: 18px; }
        .summary { width: 42%; margin-left: auto; border-collapse: collapse; }
        .summary td { padding: 5px 0; }
        .summary td:last-child { text-align: right; white-space: nowrap; font-weight: bold; }
        .summary .grand td { padding-top: 10px; border-top: 2px solid #111827; font-size: 14px; }
        .notes { margin-top: 28px; padding: 13px; background: #f9fafb; border-left: 3px solid #4f46e5; white-space: pre-line; }
        .footer { margin-top: 40px; padding-top: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; font-size: 9px; text-align: center; }
        @media print {
            .no-print { display: none !important; }
            .page { max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
        @if ($forPdf)
            .page { max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        @endif
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar no-print">
            <a class="secondary" href="{{ $backUrl }}">Retour</a>
            <button type="button" onclick="window.print()">Imprimer</button>
            <a href="{{ $pdfUrl }}">Télécharger PDF</a>
        </div>
    @endunless

    <main class="page">
        <table class="header">
            <tr>
                <td>
                    @if($company->logo_path)<img class="logo" src="{{ $forPdf ? Storage::disk('public')->path($company->logo_path) : route('administration.company.asset', 'logo') }}" alt="Logo">@endif
                    <div class="brand">{{ $company->name }}</div>
                    @if($company->legal_form)<div class="muted">{{ $company->legal_form }}</div>@endif
                    <div class="muted">Gestion commerciale et comptable</div>
                </td>
                <td class="right">
                    <h1 class="document-title">{{ $documentType }}</h1>
                    <div><strong>{{ $documentNumber }}</strong></div>
                    <span class="badge">{{ $document->status->label() }}</span>
                </td>
            </tr>
        </table>

        <table class="parties">
            <tr>
                <td>
                    <div class="label">Émetteur</div>
                    <div class="party-name">{{ $company->name }}</div>
                    @if($company->tax_identifier)<div>N° fiscal : {{ $company->tax_identifier }}</div>@endif
                    @if($company->national_identifier)<div>ID Nat : {{ $company->national_identifier }}</div>@endif
                    @if($company->cnss_number)<div>CNSS : {{ $company->cnss_number }}</div>@endif
                    @if($company->trade_register)<div>RCCM : {{ $company->trade_register }}</div>@endif
                    @if($company->address)<div>{!! nl2br(e($company->address)) !!}</div>@endif
                    @if($company->email)<div>{{ $company->email }}</div>@endif
                    @if($company->phone)<div>{{ $company->phone }}</div>@endif
                </td>
                <td>
                    <div class="label">Client</div>
                    <div class="party-name">{{ $document->party->name }}</div>
                    @if ($document->party->tax_identifier)<div>N° fiscal : {{ $document->party->tax_identifier }}</div>@endif
                    @if ($document->party->address)<div>{!! nl2br(e($document->party->address)) !!}</div>@endif
                    @if ($document->party->email)<div>{{ $document->party->email }}</div>@endif
                    @if ($document->party->phone)<div>{{ $document->party->phone }}</div>@endif
                </td>
            </tr>
        </table>

        <table style="width: 100%; margin-bottom: 20px;">
            <tr>
                <td><span class="muted">Date d’émission :</span> <strong>{{ $document->issue_date->format('d/m/Y') }}</strong></td>
                <td class="right"><span class="muted">{{ $secondaryDateLabel }} :</span> <strong>{{ $secondaryDateValue ?? $secondaryDate->format('d/m/Y') }}</strong></td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 38%">Désignation</th>
                    <th class="number">Quantité</th>
                    <th class="number">Prix HT</th>
                    <th class="number">Remise</th>
                    <th class="number">Taxe</th>
                    <th class="number">Total TTC</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document->lines as $line)
                    <tr>
                        <td><strong>{{ $line->description }}</strong><div class="sku">{{ $line->sku }} · {{ $line->unit }}</div></td>
                        <td class="number">{{ number_format((float) $line->quantity, 3, ',', ' ') }}</td>
                        <td class="number">{{ number_format((float) $line->unit_price, 2, ',', ' ') }}</td>
                        <td class="number">{{ number_format((float) $line->discount_rate, 2, ',', ' ') }} %</td>
                        <td class="number">{{ number_format((float) $line->tax_amount, 2, ',', ' ') }}</td>
                        <td class="number"><strong>{{ number_format((float) $line->total, 2, ',', ' ') }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-wrap">
            <table class="summary">
                <tr><td class="muted">Sous-total HT</td><td>{{ number_format((float) $document->subtotal, 2, ',', ' ') }} {{ $document->currency }}</td></tr>
                <tr><td class="muted">Remises</td><td>− {{ number_format((float) $document->discount_total, 2, ',', ' ') }} {{ $document->currency }}</td></tr>
                <tr><td class="muted">Taxes</td><td>{{ number_format((float) $document->tax_total, 2, ',', ' ') }} {{ $document->currency }}</td></tr>
                <tr class="grand"><td>Total TTC</td><td>{{ number_format((float) $document->total, 2, ',', ' ') }} {{ $document->currency }}</td></tr>
            </table>
        </div>

        @if ($notesText ?? $document->notes)
            <div class="notes"><div class="label">{{ $notesLabel ?? 'Notes et conditions' }}</div>{{ $notesText ?? $document->notes }}</div>
        @endif

        @if($company->bank_name || $company->mobile_money)
            <div class="notes"><div class="label">Coordonnées de paiement</div>
                @if($company->bank_name)<strong>{{ $company->bank_name }}</strong>@endif
                @if($company->bank_account_name) — {{ $company->bank_account_name }}@endif
                @if($company->bank_account_number)<br>Compte : {{ $company->bank_account_number }}@endif
                @if($company->bank_swift) · SWIFT : {{ $company->bank_swift }}@endif
                @if($company->mobile_money)<br>Mobile Money : {{ $company->mobile_money }}@endif
            </div>
        @endif
        @if($company->invoice_footer)<div class="notes"><div class="label">Informations de l’entreprise</div>{{ $company->invoice_footer }}</div>@endif
        @if($company->signature_path || $company->stamp_path)
            <div class="approval-assets">
                @if($company->signature_path)<img src="{{ $forPdf ? Storage::disk('public')->path($company->signature_path) : route('administration.company.asset', 'signature') }}" alt="Signature">@endif
                @if($company->stamp_path)<img src="{{ $forPdf ? Storage::disk('public')->path($company->stamp_path) : route('administration.company.asset', 'stamp') }}" alt="Cachet">@endif
            </div>
        @endif
        @if($company->representative_name)<div class="right"><strong>{{ $company->representative_name }}</strong>@if($company->representative_title)<br><span class="muted">{{ $company->representative_title }}</span>@endif</div>@endif
        <div class="footer">Document généré par {{ $company->name }} le {{ now()->format('d/m/Y à H:i') }}</div>
    </main>
</body>
</html>
