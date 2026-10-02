@php
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $asset = fn (string $type) => $forPdf
        ? Storage::disk('public')->path($company->{$type.'_path'})
        : route('administration.company.asset', $type);
    $isQuote = $document instanceof \App\Models\Quote;
    $netTotal = (float) $document->subtotal - (float) $document->discount_total;
    $legalIds = array_filter([
        'NIF' => $company->tax_identifier,
        'RCCM' => $company->trade_register,
        'ID Nat' => $company->national_identifier,
        'CNSS' => $company->cnss_number,
    ]);
    $missingSettings = array_keys(array_filter([
        'numéro fiscal (NIF)' => blank($company->tax_identifier),
        'RCCM' => blank($company->trade_register),
        'adresse' => blank($company->address),
        'raison sociale' => $company->name === config('app.name'),
    ]));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $documentType }} {{ $documentNumber }} — {{ $company->name }}</title>
    <style>
        @page { margin: 14mm 14mm 24mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.3; background: #e5e7eb; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #6b7280; }
        .strong { font-weight: bold; }
        .nowrap { white-space: nowrap; }
        .mono { font-family: DejaVu Sans Mono, monospace; }

        /* Barre d’outils écran */
        .toolbar { position: sticky; top: 0; z-index: 10; padding: 12px; text-align: center; background: #111827; }
        .toolbar a, .toolbar button { display: inline-block; margin: 0 4px; padding: 9px 16px; border: 0; border-radius: 6px; color: #fff; background: #4338ca; font: 600 13px system-ui, sans-serif; text-decoration: none; cursor: pointer; }
        .toolbar .secondary { background: #374151; }
        .alert { max-width: 210mm; margin: 16px auto 0; padding: 12px 16px; border: 1px solid #fcd34d; border-radius: 8px; color: #78350f; background: #fffbeb; font: 13px system-ui, sans-serif; }
        .alert a { color: #78350f; font-weight: 600; }

        .page { position: relative; max-width: 210mm; min-height: 297mm; margin: 16px auto 32px; padding: 14mm 14mm 30mm; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,.12); overflow: hidden; }
        .accent { height: 4px; margin: -14mm -14mm 8mm; background: #1e3a8a; }

        /* En-tête */
        .logo { max-width: 170px; max-height: 64px; margin-bottom: 6px; }
        .company-name { color: #111827; font-size: 17px; font-weight: bold; line-height: 1.2; }
        .company-meta { margin-top: 4px; color: #4b5563; font-size: 9px; }
        .doc-title { margin: 0; color: #1e3a8a; font-size: 26px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .meta { width: auto; margin: 6px 0 0 auto; }
        .meta td { padding: 1px 0 1px 14px; }
        .meta td:first-child { color: #6b7280; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 10px; color: #1e3a8a; background: #dbeafe; font-size: 8px; font-weight: bold; text-transform: uppercase; }

        /* Blocs émetteur / client */
        .boxes { margin-top: 14px; }
        .boxes > tbody > tr > td { width: 50%; }
        .box { padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 4px; }
        .box.client { border-color: #1e3a8a; }
        .label { margin-bottom: 5px; color: #6b7280; font-size: 7.5px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .party-name { color: #111827; font-size: 12px; font-weight: bold; }

        /* Lignes */
        .lines { margin-top: 14px; }
        .lines th { padding: 7px 6px; color: #fff; background: #1e3a8a; font-size: 7.5px; text-align: left; text-transform: uppercase; letter-spacing: .4px; }
        .lines td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; }
        .lines tbody tr:nth-child(even) td { background: #f9fafb; }
        .lines .num { text-align: right; white-space: nowrap; }
        .sku { color: #6b7280; font-size: 8px; }

        /* Totaux */
        .summary { margin-top: 14px; }
        .summary > tbody > tr > td:first-child { width: 56%; padding-right: 18px; }
        .tax-table th { padding: 4px 6px; color: #4b5563; background: #f3f4f6; font-size: 7.5px; text-align: right; text-transform: uppercase; }
        .tax-table th:first-child, .tax-table td:first-child { text-align: left; }
        .tax-table td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; text-align: right; }
        .words { margin-top: 10px; padding: 8px 10px; border-left: 3px solid #1e3a8a; background: #f9fafb; }
        .totals td { padding: 3px 8px; }
        .totals td:last-child { text-align: right; white-space: nowrap; }
        .totals .grand td { padding: 6px 8px; color: #fff; background: #1e3a8a; font-size: 12px; font-weight: bold; }
        .totals .due td { padding: 6px 8px; border-top: 2px solid #1e3a8a; border-bottom: 2px solid #1e3a8a; font-size: 11px; font-weight: bold; }

        .section { margin-top: 10px; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 4px; white-space: pre-line; }

        /* Signatures */
        .signatures { margin-top: 12px; }
        .signatures > tbody > tr > td { width: 50%; }
        .sign-box { height: 78px; padding: 8px 10px; border: 1px dashed #9ca3af; border-radius: 4px; }
        .sign-box img { max-height: 56px; max-width: 110px; margin-right: 8px; }

        /* Contrôle */
        .control { margin-top: 12px; }
        .qr { width: 78px; height: 78px; }
        .code { color: #111827; font-size: 12px; font-weight: bold; letter-spacing: 1px; }

        /* Filigrane */
        .watermark { position: absolute; top: 42%; left: 0; right: 0; color: rgba(220, 38, 38, .12); font-size: 96px; font-weight: bold; text-align: center; text-transform: uppercase; transform: rotate(-30deg); z-index: 0; }

        /* Pied de page légal */
        .footer { position: absolute; left: 14mm; right: 14mm; bottom: 10mm; padding-top: 6px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 7.5px; text-align: center; }
        
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .page { max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; overflow: visible; }
            .accent { margin: 0 0 8mm; }
            .footer { position: fixed; left: 0; right: 0; bottom: 0; }
            .watermark { position: fixed; }
        }
        @if ($forPdf)
            body { background: #fff; }
            .page { max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; overflow: visible; }
            .accent { margin: 0 0 8mm; }
            .footer { position: fixed; left: 0; right: 0; bottom: -14mm; }
            .watermark { position: fixed; }
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
        @if ($missingSettings)
            <div class="alert no-print">
                <strong>Informations de l’entreprise incomplètes :</strong> {{ implode(', ', $missingSettings) }}.
                Ces mentions sont attendues sur un document commercial.
                @can(\App\Modules\Administration\Enums\Permission::SettingsManage->value)
                    <a href="{{ route('administration.company.edit') }}">Compléter les paramètres</a>
                @endcan
            </div>
        @endif
    @endunless

    <main class="page">
        @if ($watermark)<div class="watermark">{{ $watermark }}</div>@endif
        <div class="accent"></div>

        <!-- En-tête : entreprise et identification du document -->
        <table>
            <tr>
                <td style="width: 55%">
                    @if ($company->logo_path)<img class="logo" src="{{ $asset('logo') }}" alt="{{ $company->name }}"><br>@endif
                    <div class="company-name">{{ $company->name }}@if ($company->legal_form) <span class="muted" style="font-size: 11px; font-weight: normal">{{ $company->legal_form }}</span>@endif</div>
                    <div class="company-meta">
                        @if ($company->address){!! nl2br(e($company->address)) !!}<br>@endif
                        {{ implode(' · ', array_filter([$company->phone ? 'Tél. '.$company->phone : null, $company->email, $company->website])) }}
                    </div>
                </td>
                <td class="right">
                    <h1 class="doc-title">{{ $documentType }}</h1>
                    <table class="meta">
                        <tr><td>N°</td><td class="strong nowrap">{{ $documentNumber }}</td></tr>
                        <tr><td>Date d’émission</td><td class="strong">{{ $document->issue_date->format('d/m/Y') }}</td></tr>
                        @isset($secondaryDate)
                            <tr><td>{{ $secondaryDateLabel }}</td><td class="strong">{{ $secondaryDate->format('d/m/Y') }}</td></tr>
                        @endisset
                        @if ($reference ?? null)
                            <tr><td>{{ $reference[0] }}</td><td class="strong">{{ $reference[1] }}</td></tr>
                        @endif
                        <tr><td>Devise</td><td class="strong">{{ $document->currency }}</td></tr>
                        <tr><td>Statut</td><td><span class="status">{{ $document->status->label() }}</span></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Émetteur et client -->
        <table class="boxes">
            <tr>
                <td style="padding-right: 8px">
                    <div class="box">
                        <div class="label">Émetteur</div>
                        <div class="party-name">{{ $company->name }}</div>
                        @foreach ($legalIds as $label => $value)<div><span class="muted">{{ $label }} :</span> {{ $value }}</div>@endforeach
                        @if ($company->representative_name)<div><span class="muted">Représenté par :</span> {{ $company->representative_name }}@if ($company->representative_title), {{ $company->representative_title }}@endif</div>@endif
                    </div>
                </td>
                <td style="padding-left: 8px">
                    <div class="box client">
                        <div class="label">{{ $isQuote ? 'Adressé à' : 'Facturé à' }}</div>
                        <div class="party-name">{{ $document->party->name }}</div>
                        @if ($document->party->tax_identifier)<div><span class="muted">NIF :</span> {{ $document->party->tax_identifier }}</div>@endif
                        @if ($document->party->address)<div>{!! nl2br(e($document->party->address)) !!}</div>@endif
                        @if ($document->party->phone || $document->party->email)<div>{{ implode(' · ', array_filter([$document->party->phone, $document->party->email])) }}</div>@endif
                    </div>
                </td>
            </tr>
        </table>

        <!-- Lignes -->
        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 4%">N°</th>
                    <th style="width: 36%">Désignation</th>
                    <th class="num">Qté</th>
                    <th class="num">P.U. HT</th>
                    <th class="num">Rem.</th>
                    <th class="num">TVA</th>
                    <th class="num">Montant HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document->lines as $line)
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td><span class="strong">{{ $line->description }}</span><br><span class="sku">Réf. {{ $line->sku }}</span></td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', ' '), '0'), ',') }} <span class="sku">{{ $line->unit }}</span></td>
                        <td class="num">{{ $money($line->unit_price) }}</td>
                        <td class="num">{{ (float) $line->discount_rate > 0 ? $money($line->discount_rate).' %' : '—' }}</td>
                        <td class="num">{{ $money($line->tax_rate) }} %</td>
                        <td class="num strong">{{ $money((float) $line->subtotal - (float) $line->discount_amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Récapitulatif TVA, montant en lettres et totaux -->
        <table class="summary">
            <tr>
                <td>
                    <table class="tax-table">
                        <thead><tr><th>Taux TVA</th><th>Base HT</th><th>Montant TVA</th></tr></thead>
                        <tbody>
                            @foreach ($taxBreakdown as $row)
                                <tr><td>{{ $money($row['rate']) }} %</td><td>{{ $money($row['base']) }}</td><td>{{ $money($row['tax']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="words">
                        <div class="label">{{ match (true) { $isQuote => 'Le présent devis s’élève à la somme de', $document instanceof \App\Models\CreditNote => 'Arrêté le présent avoir à la somme de', default => 'Arrêtée la présente facture à la somme de' } }}</div>
                        <span class="strong">{{ $amountInWords }}</span>&nbsp;TTC.
                    </div>
                </td>
                <td>
                    <table class="totals">
                        <tr><td class="muted">Total brut HT</td><td>{{ $money($document->subtotal) }}</td></tr>
                        @if ((float) $document->discount_total > 0)
                            <tr><td class="muted">Remises</td><td>− {{ $money($document->discount_total) }}</td></tr>
                        @endif
                        <tr><td class="muted">Total net HT</td><td>{{ $money($netTotal) }}</td></tr>
                        <tr><td class="muted">Total TVA</td><td>{{ $money($document->tax_total) }}</td></tr>
                        <tr class="grand"><td>Total TTC</td><td>{{ $money($document->total) }} {{ $document->currency }}</td></tr>
                        @if ($settlement ?? null)
                            @if ($settlement['credited'] > 0)<tr><td class="muted">Avoirs imputés</td><td>− {{ $money($settlement['credited']) }}</td></tr>@endif
                            @if ($settlement['paid'] > 0)<tr><td class="muted">Règlements reçus</td><td>− {{ $money($settlement['paid']) }}</td></tr>@endif
                            <tr class="due"><td>Net à payer</td><td>{{ $money($settlement['balance']) }} {{ $document->currency }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        @if ($notesText ?? $document->notes)
            <div class="section"><div class="label">{{ $notesLabel ?? 'Notes' }}</div>{{ $notesText ?? $document->notes }}</div>
        @endif

        @if (! $isQuote || $company->invoice_footer)
            <table style="margin-top: 0">
                <tr>
                    @if ($company->bank_name || $company->mobile_money)
                        <td style="width: 50%; padding-right: 8px">
                            <div class="section" style="white-space: normal">
                                <div class="label">Modalités de paiement</div>
                                @if ($company->bank_name)<span class="strong">{{ $company->bank_name }}</span>@if ($company->bank_account_name) — {{ $company->bank_account_name }}@endif<br>@endif
                                @if ($company->bank_account_number)Compte : <span class="mono">{{ $company->bank_account_number }}</span><br>@endif
                                @if ($company->bank_swift)SWIFT : <span class="mono">{{ $company->bank_swift }}</span><br>@endif
                                @if ($company->mobile_money)Mobile Money : {{ $company->mobile_money }}<br>@endif
                                @if ($documentNumber && ! $isQuote)<span class="muted">Merci de rappeler la référence {{ $documentNumber }} lors du paiement.</span>@endif
                            </div>
                        </td>
                    @endif
                    @if ($company->invoice_footer)
                        <td style="padding-left: {{ $company->bank_name || $company->mobile_money ? '8px' : '0' }}">
                            <div class="section"><div class="label">Conditions</div>{{ $company->invoice_footer }}</div>
                        </td>
                    @endif
                </tr>
            </table>
        @endif

        @if ($isQuote)
            <!-- Accord du client -->
            <table class="signatures">
                <tr>
                    <td style="padding-right: 8px">
                        <div class="sign-box">
                            <div class="label">Bon pour accord — le client</div>
                            <div class="muted">Date, nom, signature et cachet précédés de la mention « Bon pour accord »</div>
                        </div>
                    </td>
                    <td style="padding-left: 8px">@include('documents._company-signature')</td>
                </tr>
            </table>
        @endif

        <!-- Éléments de contrôle et signature de l’émetteur -->
        <table class="signatures control">
            <tr>
                <td style="padding-right: 8px">
                    <table>
                        <tr>
                            @if ($qrCode)
                                <td style="width: 84px"><img class="qr" src="{{ $qrCode }}" alt="QR code de vérification"></td>
                            @endif
                            <td>
                                @if ($fingerprint)
                                    <div class="label">Code de contrôle</div>
                                    <div class="code mono">{{ $fingerprint }}</div>
                                    <div class="muted">Scannez le QR code pour vérifier l’authenticité du document, son code et son montant.</div>
                                @else
                                    <div class="label">Document provisoire</div>
                                    <div class="muted">Ce brouillon n’a pas de numéro définitif : il n’a aucune valeur comptable ni fiscale.</div>
                                @endif
                                <div class="muted" style="margin-top: 4px; font-size: 7.5px">
                                    {{ $document->lines->count() }} ligne(s)
                                    @if ($document->creator) · Établi par {{ $document->creator->name }}@endif
                                    @if ($document instanceof \App\Models\Invoice && $document->validated_at) · Validée le {{ $document->validated_at->format('d/m/Y à H:i') }}@endif
                                    · Édité le {{ now()->format('d/m/Y à H:i') }}
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="padding-left: 8px">
                    @unless ($isQuote)
                        @include('documents._company-signature')
                    @endunless
                </td>
            </tr>
        </table>

        <!-- Pied de page légal -->
        <div class="footer">
            <span class="strong">{{ $company->name }}</span>@if ($company->legal_form) — {{ $company->legal_form }}@endif
            @foreach ($legalIds as $label => $value) · {{ $label }} {{ $value }}@endforeach
                    </div>
    </main>
</body>
</html>
