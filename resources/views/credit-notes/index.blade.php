@php $money = fn ($value) => number_format((float) $value, 2, ',', ' '); @endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">Ventes</p>
            <h1 class="text-2xl font-semibold text-gray-900">Avoirs clients</h1>
            <p class="mt-1 text-sm text-gray-500">Les avoirs corrigent tout ou partie d’une facture validée. Ils se créent depuis la fiche de la facture concernée.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            @foreach ($summary as $currency => $row)
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['Total crédité', $money($row['total']).' '.$currency, 'text-amber-700', 'Depuis le début'],
                        ['Crédité ce mois', $money($row['month']).' '.$currency, 'text-gray-900', ucfirst(today()->locale('fr')->translatedFormat('F Y'))],
                        ['Avoirs émis', $row['count'], 'text-gray-900', 'En '.$currency],
                    ] as [$label, $value, $color, $hint])
                        <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-5">
                            <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
                            <p class="mt-2 text-xl font-semibold sm:text-2xl {{ $color }}">{{ $value }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
                        </article>
                    @endforeach
                </div>
            @endforeach

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="flex justify-end border-b border-gray-200 px-4 py-3">
                    <form method="GET" class="flex w-full gap-2 lg:w-auto">
                        <div class="relative flex-1 lg:w-80">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m21 21-4.3-4.3M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z"/></svg>
                            <input name="search" value="{{ $search }}" placeholder="Avoir, facture ou client" class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Rechercher</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3">Avoir</th>
                                <th class="px-6 py-3">Client</th>
                                <th class="px-6 py-3">Facture d’origine</th>
                                <th class="px-6 py-3">Motif</th>
                                <th class="px-6 py-3 text-right">Montant crédité</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($creditNotes as $creditNote)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <a href="{{ route('credit-notes.show', $creditNote) }}" class="font-semibold text-indigo-600 hover:text-indigo-500" wire:navigate>{{ $creditNote->number }}</a>
                                        <p class="text-xs text-gray-500">du {{ $creditNote->issue_date->format('d/m/Y') }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $creditNote->party->name }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm"><a href="{{ route('invoices.show', $creditNote->invoice) }}" class="text-indigo-600 hover:text-indigo-500" wire:navigate>{{ $creditNote->invoice->number }}</a></td>
                                    <td class="max-w-xs truncate px-6 py-4 text-sm text-gray-600" title="{{ $creditNote->reason }}">{{ $creditNote->reason }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold text-amber-700">{{ $money($creditNote->total) }} <span class="text-xs font-normal text-gray-400">{{ $creditNote->currency }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center">
                                        <p class="font-medium text-gray-900">Aucun avoir</p>
                                        <p class="mt-1 text-sm text-gray-500">{{ $search ? 'Aucun résultat pour cette recherche.' : 'Ouvrez une facture validée et choisissez « Créer un avoir » pour la corriger.' }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($creditNotes->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $creditNotes->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-app-layout>
