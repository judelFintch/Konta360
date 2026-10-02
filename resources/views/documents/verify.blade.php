<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Vérification — {{ $documentType }} {{ $document->number }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
    <main class="mx-auto max-w-lg px-4 py-10">
        <div class="mb-6 flex items-center gap-3">
            @if($company->logo_path)
                <img src="{{ route('documents.verify.logo') }}" alt="" class="h-12 w-auto max-w-[140px] object-contain">
            @endif
            <div>
                <p class="text-lg font-bold">{{ $company->name }}</p>
                @if($company->trade_register)<p class="text-xs text-gray-500">RCCM {{ $company->trade_register }}</p>@endif
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
            @if($cancelled)
                <div class="flex items-center gap-3 bg-red-50 px-6 py-4 text-red-800">
                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    <div><p class="font-semibold">Document authentique mais non valable</p><p class="text-sm">Ce document a été émis par {{ $company->name }} puis {{ strtolower($document->status->label()) }}.</p></div>
                </div>
            @else
                <div class="flex items-center gap-3 bg-emerald-50 px-6 py-4 text-emerald-800">
                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                    <div><p class="font-semibold">Document authentique</p><p class="text-sm">Ce document a bien été émis par {{ $company->name }}.</p></div>
                </div>
            @endif

            <dl class="divide-y divide-gray-100 px-6 text-sm">
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Document</dt><dd class="font-semibold">{{ $documentType }} {{ $document->number }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Date d’émission</dt><dd>{{ $document->issue_date->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Client</dt><dd class="text-right">{{ $document->party->name }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Nombre de lignes</dt><dd>{{ $document->lines->count() }}</dd></div>
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Montant TTC</dt><dd class="font-semibold">{{ number_format((float) $document->total, 2, ',', ' ') }} {{ $document->currency }}</dd></div>
                @if(! is_null($balanceDue) && ! $cancelled)
                    <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Situation</dt><dd>{{ $document->paymentLabel() }}</dd></div>
                @endif
                <div class="flex justify-between gap-4 py-3"><dt class="text-gray-500">Statut</dt><dd>{{ $document->status->label() }}</dd></div>
                <div class="py-3">
                    <dt class="text-gray-500">Code de contrôle</dt>
                    <dd class="mt-1 font-mono text-base font-semibold tracking-wider">{{ $fingerprint }}</dd>
                </div>
            </dl>

            <p class="bg-gray-50 px-6 py-4 text-xs text-gray-500">
                Comparez le code de contrôle et le montant ci-dessus avec ceux imprimés sur votre document.
                S’ils diffèrent, le document a pu être modifié : contactez {{ $company->name }}@if($company->phone) au {{ $company->phone }}@endif.
            </p>
        </div>
    </main>
</body>
</html>
