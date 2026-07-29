@php use App\Modules\Administration\Enums\Permission; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm font-medium text-indigo-600">États comptables</p><h1 class="text-2xl font-semibold text-gray-900">Balance générale</h1></div>
            @can(Permission::ReportsExport->value)
                <a href="{{ route('accounting.trial-balance.pdf', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'currency' => $currency]) }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Télécharger PDF</a>
            @endcan
        </div>
    </x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('accounting.reports._navigation')
        <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[1fr_1fr_160px_auto]">
            <div><label class="text-xs font-medium text-gray-500">Du</label><input type="date" name="date_from" value="{{ $dateFrom }}" class="mt-1 block w-full rounded-md border-gray-300"></div>
            <div><label class="text-xs font-medium text-gray-500">Au</label><input type="date" name="date_to" value="{{ $dateTo }}" class="mt-1 block w-full rounded-md border-gray-300"></div>
            <div><label class="text-xs font-medium text-gray-500">Devise</label><select name="currency" class="mt-1 block w-full rounded-md border-gray-300"><option value="CDF" @selected($currency === 'CDF')>CDF</option><option value="USD" @selected($currency === 'USD')>USD</option></select></div>
            <x-secondary-button type="submit" class="self-end">Actualiser</x-secondary-button>
        </form>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-6 py-3">Compte</th><th class="px-6 py-3">Intitulé</th><th class="px-6 py-3 text-right">Mouvements débit</th><th class="px-6 py-3 text-right">Mouvements crédit</th><th class="px-6 py-3 text-right">Solde débiteur</th><th class="px-6 py-3 text-right">Solde créditeur</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($accounts as $account)
                        @php $debit = (float) $account->total_debit; $credit = (float) $account->total_credit; @endphp
                        <tr><td class="px-6 py-4 font-semibold">{{ $account->code }}</td><td class="px-6 py-4 text-sm">{{ $account->name }}</td><td class="px-6 py-4 text-right">{{ number_format($debit, 2, ',', ' ') }}</td><td class="px-6 py-4 text-right">{{ number_format($credit, 2, ',', ' ') }}</td><td class="px-6 py-4 text-right font-medium">{{ number_format(max(0, $debit - $credit), 2, ',', ' ') }}</td><td class="px-6 py-4 text-right font-medium">{{ number_format(max(0, $credit - $debit), 2, ',', ' ') }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-14 text-center text-gray-500">Aucun mouvement pour cette période et cette devise.</td></tr>
                    @endforelse
                </tbody>
                @php $totalDebit = $accounts->sum(fn ($a) => (float) $a->total_debit); $totalCredit = $accounts->sum(fn ($a) => (float) $a->total_credit); @endphp
                <tfoot class="bg-indigo-950 font-semibold text-white"><tr><td colspan="2" class="px-6 py-4">Totaux {{ $currency }}</td><td class="px-6 py-4 text-right">{{ number_format($totalDebit, 2, ',', ' ') }}</td><td class="px-6 py-4 text-right">{{ number_format($totalCredit, 2, ',', ' ') }}</td><td class="px-6 py-4 text-right">{{ number_format($accounts->sum(fn ($a) => max(0, (float) $a->total_debit - (float) $a->total_credit)), 2, ',', ' ') }}</td><td class="px-6 py-4 text-right">{{ number_format($accounts->sum(fn ($a) => max(0, (float) $a->total_credit - (float) $a->total_debit)), 2, ',', ' ') }}</td></tr></tfoot>
            </table>
        </div></div>
        <p class="mt-4 text-sm text-gray-500">Période du {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') }} · Les totaux débit et crédit doivent être identiques.</p>
    </div></div>
</x-app-layout>
