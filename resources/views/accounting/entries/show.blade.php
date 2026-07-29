<x-app-layout>
    <x-slot name="header">
        <div><p class="text-sm font-medium text-indigo-600">{{ $entry->journal->code }} — {{ $entry->journal->name }}</p><h1 class="text-2xl font-semibold text-gray-900">{{ $entry->number }}</h1></div>
    </x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <dl class="grid gap-5 border-b border-gray-200 pb-6 sm:grid-cols-4">
                <div><dt class="text-xs uppercase tracking-wide text-gray-500">Date</dt><dd class="mt-1 font-semibold">{{ $entry->entry_date->format('d/m/Y') }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-500">Devise</dt><dd class="mt-1 font-semibold">{{ $entry->currency }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-500">Statut</dt><dd class="mt-1 font-semibold">{{ $entry->status->label() }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-gray-500">Créée par</dt><dd class="mt-1 font-semibold">{{ $entry->creator->name }}</dd></div>
            </dl>
            <h2 class="mt-6 text-lg font-semibold text-gray-900">{{ $entry->label }}</h2>
            <div class="mt-5 overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr class="text-left text-xs uppercase tracking-wide text-gray-500"><th class="px-5 py-3">Compte</th><th class="px-5 py-3">Libellé</th><th class="px-5 py-3 text-right">Débit</th><th class="px-5 py-3 text-right">Crédit</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($entry->lines as $line)<tr><td class="px-5 py-4"><span class="font-semibold">{{ $line->account->code }}</span><p class="text-sm text-gray-500">{{ $line->account->name }}</p></td><td class="px-5 py-4 text-sm">{{ $line->description }}</td><td class="px-5 py-4 text-right font-medium">{{ $line->debit > 0 ? number_format((float) $line->debit, 2, ',', ' ').' '.$entry->currency : '—' }}</td><td class="px-5 py-4 text-right font-medium">{{ $line->credit > 0 ? number_format((float) $line->credit, 2, ',', ' ').' '.$entry->currency : '—' }}</td></tr>@endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-semibold"><tr><td colspan="2" class="px-5 py-4 text-right">Totaux</td><td class="px-5 py-4 text-right">{{ number_format($entry->totalDebit(), 2, ',', ' ') }} {{ $entry->currency }}</td><td class="px-5 py-4 text-right">{{ number_format($entry->totalCredit(), 2, ',', ' ') }} {{ $entry->currency }}</td></tr></tfoot>
            </table></div>
        </article>
    </div></div>
</x-app-layout>
