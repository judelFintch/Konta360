<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Ventes</p><h1 class="text-2xl font-semibold text-gray-900">Avoirs clients</h1></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if (session('success'))<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <form method="GET" class="mb-6 flex gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <input name="search" value="{{ $search }}" placeholder="Avoir, facture ou client" class="min-w-0 flex-1 rounded-md border-gray-300">
            <x-secondary-button type="submit">Rechercher</x-secondary-button>
        </form>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="px-6 py-3">Avoir</th><th class="px-6 py-3">Facture</th><th class="px-6 py-3">Client</th><th class="px-6 py-3">Date</th><th class="px-6 py-3 text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($creditNotes as $creditNote)
                        <tr><td class="px-6 py-4"><a href="{{ route('credit-notes.show', $creditNote) }}" class="font-semibold text-indigo-600">{{ $creditNote->number }}</a></td><td class="px-6 py-4"><a href="{{ route('invoices.show', $creditNote->invoice) }}" class="text-indigo-600">{{ $creditNote->invoice->number }}</a></td><td class="px-6 py-4">{{ $creditNote->party->name }}</td><td class="px-6 py-4">{{ $creditNote->issue_date->format('d/m/Y') }}</td><td class="px-6 py-4 text-right font-semibold">{{ number_format((float) $creditNote->total, 2, ',', ' ') }} {{ $creditNote->currency }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-14 text-center text-gray-500">Aucun avoir émis.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>@if($creditNotes->hasPages())<div class="border-t px-6 py-4">{{ $creditNotes->links() }}</div>@endif</div>
    </div></div>
</x-app-layout>
