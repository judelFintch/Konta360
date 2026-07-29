@php use App\Modules\Administration\Enums\Permission; use App\Modules\Quotes\Enums\QuoteStatus; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">Ventes</p>
                <h1 class="text-2xl font-semibold text-gray-900">Devis</h1>
                <p class="mt-1 text-sm text-gray-500">Préparez vos propositions commerciales et suivez-les jusqu'à leur transformation en facture.</p>
            </div>
            @can(Permission::QuotesCreate->value)
                <a href="{{ route('quotes.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Nouveau devis</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[1fr_220px_auto]">
                <input name="search" value="{{ $search }}" placeholder="Numéro ou client" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tous les statuts</option>
                    @foreach (QuoteStatus::cases() as $quoteStatus)
                        <option value="{{ $quoteStatus->value }}" @selected($status === $quoteStatus->value)>{{ $quoteStatus->label() }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">Filtrer</x-secondary-button>
            </form>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-6 py-3">Numéro</th><th class="px-6 py-3">Client</th><th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Statut</th><th class="px-6 py-3 text-right">Total TTC</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($quotes as $quote)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><a href="{{ route('quotes.show', $quote) }}" class="font-semibold text-indigo-600 hover:text-indigo-500">{{ $quote->number }}</a></td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $quote->party->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $quote->issue_date->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4"><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ $quote->status->label() }}</span></td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ number_format((float) $quote->total, 2, ',', ' ') }} {{ $quote->currency }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-14 text-center"><p class="font-medium text-gray-900">Aucun devis trouvé</p><p class="mt-1 text-sm text-gray-500">Créez votre premier devis commercial.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($quotes->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $quotes->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-app-layout>
