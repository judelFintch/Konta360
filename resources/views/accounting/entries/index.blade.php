<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-medium text-indigo-600">Comptabilité générale</p><h1 class="text-2xl font-semibold text-gray-900">Journal des écritures</h1></div>@can(\App\Modules\Administration\Enums\Permission::AccountingEntriesCreate->value)<a href="{{ route('accounting.entries.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Nouvelle écriture</a>@endcan</div>
    </x-slot>

    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('accounting.reports._navigation')
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Le plan comptable initial est provisoire. Les comptes 411, 4431, 70, 512 et 571 doivent être validés avant la production.
        </div>
        <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[1fr_180px_140px_auto]">
            <input name="search" value="{{ $search }}" placeholder="Numéro ou libellé" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <select name="journal" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tous les journaux</option>
                @foreach ($journals as $item)<option value="{{ $item->code }}" @selected($journal === $item->code)>{{ $item->code }} — {{ $item->name }}</option>@endforeach
            </select>
            <select name="currency" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Toutes devises</option><option value="CDF" @selected($currency === 'CDF')>CDF</option><option value="USD" @selected($currency === 'USD')>USD</option>
            </select>
            <x-secondary-button type="submit">Filtrer</x-secondary-button>
        </form>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-6 py-3">Écriture</th><th class="px-6 py-3">Journal</th><th class="px-6 py-3">Libellé</th><th class="px-6 py-3">Statut</th><th class="px-6 py-3 text-right">Débit</th><th class="px-6 py-3 text-right">Crédit</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($entries as $entry)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><a href="{{ route('accounting.entries.show', $entry) }}" class="font-semibold text-indigo-600">{{ $entry->number }}</a><p class="text-sm text-gray-500">{{ $entry->entry_date->format('d/m/Y') }}</p></td>
                            <td class="px-6 py-4 text-sm font-semibold">{{ $entry->journal->code }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $entry->label }}</td>
                            <td class="px-6 py-4"><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">{{ $entry->status->label() }}</span></td>
                            <td class="px-6 py-4 text-right font-medium">{{ number_format((float) $entry->debit_total, 2, ',', ' ') }} {{ $entry->currency }}</td>
                            <td class="px-6 py-4 text-right font-medium">{{ number_format((float) $entry->credit_total, 2, ',', ' ') }} {{ $entry->currency }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-14 text-center"><p class="font-medium text-gray-900">Aucune écriture</p><p class="mt-1 text-sm text-gray-500">Les factures validées et règlements alimenteront automatiquement le journal.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>@if ($entries->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $entries->links() }}</div>@endif</div>
    </div></div>
</x-app-layout>
