@php use App\Modules\Administration\Enums\Permission; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm font-medium text-indigo-600">États financiers</p><h1 class="text-2xl font-semibold text-gray-900">Bilan</h1><p class="mt-1 text-sm text-gray-500">Photographie du patrimoine de l'entreprise à une date donnée : ce qu'elle possède (actif) et comment c'est financé (passif).</p></div>
            @can(Permission::ReportsExport->value)<a href="{{ route('financial-statements.balance-sheet.pdf', ['date_to' => $dateTo, 'currency' => $currency]) }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Télécharger PDF</a>@endcan
        </div>
    </x-slot>
    <div class="py-10"><div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        @include('accounting.reports._financial-navigation')
        <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[1fr_160px_auto]">
            <div><label class="text-xs font-medium text-gray-500">Situation au</label><input type="date" name="date_to" value="{{ $dateTo }}" class="mt-1 block w-full rounded-md border-gray-300"></div>
            <div><label class="text-xs font-medium text-gray-500">Devise</label><select name="currency" class="mt-1 block w-full rounded-md border-gray-300"><option value="CDF" @selected($currency === 'CDF')>CDF</option><option value="USD" @selected($currency === 'USD')>USD</option></select></div>
            <x-secondary-button type="submit" class="self-end">Actualiser</x-secondary-button>
        </form>
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <h2 class="bg-indigo-950 px-6 py-4 font-semibold text-white">Actif</h2>
                <div class="divide-y divide-gray-100">@forelse ($assets as $account)<div class="flex justify-between px-6 py-4"><span><strong>{{ $account->code }}</strong> — {{ $account->name }}</span><span class="font-medium">{{ number_format((float) $account->total_debit - (float) $account->total_credit, 2, ',', ' ') }}</span></div>@empty<div class="px-6 py-8 text-gray-500">Aucun actif.</div>@endforelse</div>
                <div class="flex justify-between border-t bg-indigo-50 px-6 py-5 font-semibold text-indigo-950"><span>Total actif</span><span>{{ number_format($totalAssets, 2, ',', ' ') }} {{ $currency }}</span></div>
            </section>
            <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <h2 class="bg-indigo-950 px-6 py-4 font-semibold text-white">Passif et capitaux propres</h2>
                <div class="divide-y divide-gray-100">@foreach ($liabilities as $account)<div class="flex justify-between px-6 py-4"><span><strong>{{ $account->code }}</strong> — {{ $account->name }}</span><span class="font-medium">{{ number_format((float) $account->total_credit - (float) $account->total_debit, 2, ',', ' ') }}</span></div>@endforeach
                    @foreach ($equity as $account)<div class="flex justify-between px-6 py-4"><span><strong>{{ $account->code }}</strong> — {{ $account->name }}</span><span class="font-medium">{{ number_format((float) $account->total_credit - (float) $account->total_debit, 2, ',', ' ') }}</span></div>@endforeach
                    <div class="flex justify-between px-6 py-4"><span>Résultat cumulé</span><span class="font-medium">{{ number_format($retainedIncome, 2, ',', ' ') }}</span></div>
                </div>
                <div class="flex justify-between border-t bg-indigo-50 px-6 py-5 font-semibold text-indigo-950"><span>Total passif</span><span>{{ number_format($totalLiabilitiesAndEquity, 2, ',', ' ') }} {{ $currency }}</span></div>
            </section>
        </div>
        <div class="mt-6 rounded-lg px-4 py-3 text-sm font-semibold {{ abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01 ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800' }}">Contrôle : Actif {{ number_format($totalAssets, 2, ',', ' ') }} = Passif {{ number_format($totalLiabilitiesAndEquity, 2, ',', ' ') }} {{ $currency }}</div>
    </div></div>
</x-app-layout>
