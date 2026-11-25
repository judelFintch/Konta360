@php
    $legalIds = array_filter([
        'RCCM' => $company->trade_register,
        'ID Nat' => $company->national_identifier,
        'NIF' => $company->tax_identifier,
    ]);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $company->name }}</title>
    <style>
        @page { margin: 12mm 11mm 17mm; }
        body { margin: 0; color: #18212f; font-family: DejaVu Sans, sans-serif; font-size: 8px; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .company-name { color: #111827; font-size: 12px; font-weight: bold; }
        .company-meta { margin-top: 2px; color: #4b5563; font-size: 7.5px; }
        .doc-title { color: #312e81; font-size: 15px; font-weight: bold; text-transform: uppercase; text-align: right; }
        .doc-meta { margin-top: 3px; color: #374151; text-align: right; }
        .rule { margin: 8px 0 10px; border-top: 2px solid #312e81; }
        h2 { margin: 12px 0 5px; color: #312e81; font-size: 10px; }
        h3 { margin: 10px 0 4px; color: #111827; font-size: 9px; }
        .grid th { padding: 5px 4px; border: 1px solid #312e81; color: #fff; background: #312e81; font-size: 7px; text-align: left; text-transform: uppercase; }
        .grid td { padding: 4px; border: 1px solid #d1d5db; vertical-align: top; }
        .grid tr.carry td { background: #eef2ff; font-style: italic; font-weight: bold; }
        .grid tr.subtotal td { background: #f3f4f6; font-weight: bold; }
        .grid tr.total td { border-color: #111827; color: #fff; background: #111827; font-weight: bold; }
        .grid tr.blank td { height: 16px; }
        .num, .grid th.num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .muted { color: #6b7280; }
        .small { font-size: 6.5px; }
        .neg { color: #b91c1c; }
        .strong { font-weight: bold; }
        .box { margin-top: 8px; padding: 6px 8px; border: 1px solid #c7d2fe; background: #f5f7ff; }
        .summary td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        .summary tr.result td { border-top: 1.5px solid #111827; border-bottom: 0; font-size: 9px; font-weight: bold; }
        .ok { color: #047857; font-weight: bold; }
        .warn { color: #b45309; font-weight: bold; }
        .signatures { margin-top: 18px; page-break-inside: avoid; }
        .signatures td { width: 33%; padding: 0 6px; vertical-align: top; }
        .sign-box { height: 62px; padding: 5px 6px; border: 1px solid #9ca3af; }
        .sign-label { font-weight: bold; text-transform: uppercase; font-size: 7px; }
        .page-break { page-break-before: always; }
        .footer { position: fixed; bottom: -11mm; left: 0; right: 80px; color: #6b7280; font-size: 6.5px; }
    </style>
</head>
<body>
    <div class="footer">
        {{ $company->name }} — {{ $title }} · Édité le {{ $printedAt->format('d/m/Y à H:i') }} par {{ $printedBy?->name }} · Konta360
    </div>

    <table class="head">
        <tr>
            <td style="width: 55%">
                @if ($logoSize = $company->brandImageSize('logo', 150, 46, balanced: true))
                    <img src="{{ $company->brandAssetPath('logo') }}" alt="{{ $company->name }}" style="width: {{ $logoSize[0] }}px; height: {{ $logoSize[1] }}px; margin-bottom: 3px">
                @endif
                <div class="company-name">{{ $company->name }}@if ($company->legal_form) <span style="font-weight: normal">({{ $company->legal_form }})</span>@endif</div>
                <div class="company-meta">
                    @if ($company->address){{ $company->address }}<br>@endif
                    @foreach ($legalIds as $label => $value){{ $label }} : {{ $value }}@if (! $loop->last) · @endif @endforeach
                    @if ($company->phone || $company->email)<br>{{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}@endif
                </div>
            </td>
            <td style="width: 45%">
                <div class="doc-title">{{ $title }}</div>
                <div class="doc-meta">@yield('subtitle')</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    @yield('content')
</body>
</html>
