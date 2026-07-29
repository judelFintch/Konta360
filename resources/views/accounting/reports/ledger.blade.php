<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">États comptables</p><h1 class="text-2xl font-semibold text-gray-900">Grand livre</h1><p class="mt-1 text-sm text-gray-500">Détail chronologique des mouvements d'un compte comptable et de son solde progressif.</p></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('accounting.reports._navigation')
        <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[240px_1fr_1fr_140px_auto]">
            <div><label class="text-xs font-medium text-gray-500">Compte</label><select name="account" required class="mt-1 block w-full rounded-md border-gray-300"><option value="">Sélectionner</option>@foreach ($accounts as $item)<option value="{{ $item->id }}" @selected($account?->id === $item->id)>{{ $item->code }} — {{ $item->name }}</option>@endforeach</select></div>
            <div><label class="text-xs font-medium text-gray-500">Du</label><input type="date" name="date_from" value="{{ $dateFrom }}" class="mt-1 block w-full rounded-md border-gray-300"></div>
            <div><label class="text-xs font-medium text-gray-500">Au</label><input type="date" name="date_to" value="{{ $dateTo }}" class="mt-1 block w-full rounded-md border-gray-300"></div>
            <div><label class="text-xs font-medium text-gray-500">Devise</label><select name="currency" class="mt-1 block w-full rounded-md border-gray-300"><option value="CDF" @selected($currency === 'CDF')>CDF</option><option value="USD" @selected($currency === 'USD')>USD</option></select></div>
            <x-secondary-button type="submit" class="self-end">Afficher</x-secondary-button>
        </form>
        @if ($account)
            <div class="mb-4"><h2 class="text-lg font-semibold text-gray-900">{{ $account->code }} — {{ $account->name }}</h2><p class="text-sm text-gray-500">Mouvements en {{ $currency }}</p></div>
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><th class="px-5 py-3">Date</th><th class="px-5 py-3">Écriture</th><th class="px-5 py-3">Journal</th><th class="px-5 py-3">Libellé</th><th class="px-5 py-3 text-right">Débit</th><th class="px-5 py-3 text-right">Crédit</th><th class="px-5 py-3 text-right">Solde</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @php $running = 0; @endphp
                        @forelse ($lines as $line)
                            @php $running += (float) $line->debit - (float) $line->credit; @endphp
                            <tr><td class="px-5 py-4 text-sm">{{ $line->entry->entry_date->format('d/m/Y') }}</td><td class="px-5 py-4"><a href="{{ route('accounting.entries.show', $line->entry) }}" class="font-semibold text-indigo-600">{{ $line->entry->number }}</a></td><td class="px-5 py-4 text-sm font-medium">{{ $line->entry->journal->code }}</td><td class="px-5 py-4 text-sm">{{ $line->description }}</td><td class="px-5 py-4 text-right">{{ number_format((float) $line->debit, 2, ',', ' ') }}</td><td class="px-5 py-4 text-right">{{ number_format((float) $line->credit, 2, ',', ' ') }}</td><td class="px-5 py-4 text-right font-semibold">{{ number_format($running, 2, ',', ' ') }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">Aucun mouvement sur ce compte.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-indigo-950 font-semibold text-white"><tr><td colspan="4" class="px-5 py-4">Totaux</td><td class="px-5 py-4 text-right">{{ number_format($lines->sum(fn ($l) => (float) $l->debit), 2, ',', ' ') }}</td><td class="px-5 py-4 text-right">{{ number_format($lines->sum(fn ($l) => (float) $l->credit), 2, ',', ' ') }}</td><td class="px-5 py-4 text-right">{{ number_format($running, 2, ',', ' ') }}</td></tr></tfoot>
                </table>
            </div></div>
        @else
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 py-16 text-center text-gray-500">Sélectionnez un compte pour afficher son grand livre.</div>
        @endif
    </div></div>
</x-app-layout>
