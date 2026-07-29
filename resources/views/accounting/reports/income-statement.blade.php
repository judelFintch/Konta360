@php use App\Modules\Administration\Enums\Permission; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm font-medium text-indigo-600">États financiers</p><h1 class="text-2xl font-semibold text-gray-900">Compte de résultat</h1></div>
            @can(Permission::ReportsExport->value)<a href="{{ route('financial-statements.income-statement.pdf', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'currency' => $currency]) }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Télécharger PDF</a>@endcan
        </div>
    </x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        @include('accounting.reports._financial-navigation')
        <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[1fr_1fr_150px_auto]">
            <div><label class="text-xs font-medium text-gray-500">Du</label><input type="date" name="date_from" value="{{ $dateFrom }}" class="mt-1 block w-full rounded-md border-gray-300"></div>
            <div><label class="text-xs font-medium text-gray-500">Au</label><input type="date" name="date_to" value="{{ $dateTo }}" class="mt-1 block w-full rounded-md border-gray-300"></div>
            <div><label class="text-xs font-medium text-gray-500">Devise</label><select name="currency" class="mt-1 block w-full rounded-md border-gray-300"><option value="CDF" @selected($currency === 'CDF')>CDF</option><option value="USD" @selected($currency === 'USD')>USD</option></select></div>
            <x-secondary-button type="submit" class="self-end">Actualiser</x-secondary-button>
        </form>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 font-semibold text-gray-900">Produits</div>
            <div class="divide-y divide-gray-100">@forelse ($revenues as $account)<div class="flex justify-between px-6 py-4"><span><strong>{{ $account->code }}</strong> — {{ $account->name }}</span><span class="font-medium">{{ number_format((float) $account->total_credit - (float) $account->total_debit, 2, ',', ' ') }} {{ $currency }}</span></div>@empty<div class="px-6 py-6 text-sm text-gray-500">Aucun produit.</div>@endforelse</div>
            <div class="flex justify-between border-y border-gray-200 bg-indigo-50 px-6 py-4 font-semibold text-indigo-950"><span>Total des produits</span><span>{{ number_format($totalRevenue, 2, ',', ' ') }} {{ $currency }}</span></div>
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 font-semibold text-gray-900">Charges</div>
            <div class="divide-y divide-gray-100">@forelse ($expenses as $account)<div class="flex justify-between px-6 py-4"><span><strong>{{ $account->code }}</strong> — {{ $account->name }}</span><span class="font-medium">{{ number_format((float) $account->total_debit - (float) $account->total_credit, 2, ',', ' ') }} {{ $currency }}</span></div>@empty<div class="px-6 py-6 text-sm text-gray-500">Aucune charge comptabilisée.</div>@endforelse</div>
            <div class="flex justify-between border-t border-gray-200 bg-gray-100 px-6 py-4 font-semibold"><span>Total des charges</span><span>{{ number_format($totalExpense, 2, ',', ' ') }} {{ $currency }}</span></div>
            <div class="flex justify-between bg-indigo-950 px-6 py-5 text-lg font-semibold text-white"><span>Résultat net</span><span>{{ number_format($netIncome, 2, ',', ' ') }} {{ $currency }}</span></div>
        </div>
        <p class="mt-4 text-sm text-gray-500">Résultat calculé sur les écritures comptabilisées du {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') }}.</p>
    </div></div>
</x-app-layout>
