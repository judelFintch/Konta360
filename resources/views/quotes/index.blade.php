@php
    use App\Modules\Administration\Enums\Permission;
    use App\Modules\Quotes\Enums\QuoteStatus;

    $money = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $tabs = ['' => ['Tous', $counts->sum()]] + collect(QuoteStatus::cases())
        ->mapWithKeys(fn (QuoteStatus $case) => [$case->value => [match ($case) {
            QuoteStatus::Draft => 'Brouillons',
            QuoteStatus::Sent => 'Envoyés',
            QuoteStatus::Accepted => 'Acceptés',
            QuoteStatus::Rejected => 'Refusés',
            QuoteStatus::Cancelled => 'Annulés',
        }, $counts[$case->value] ?? 0]])
        ->all();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">Ventes</p>
                <h1 class="text-2xl font-semibold text-gray-900">Devis</h1>
                <p class="mt-1 text-sm text-gray-500">Préparez vos propositions, suivez les réponses et convertissez les devis acceptés en factures.</p>
            </div>
            @can(Permission::QuotesCreate->value)
                <a href="{{ route('quotes.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500" wire:navigate>+ Nouveau devis</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            {{-- Indicateurs --}}
            @foreach ($summary as $currency => $row)
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach ([
                        ['Brouillons', $row['draft'], 'text-gray-900', 'À finaliser et envoyer'],
                        ['En attente de réponse', $row['pending'], 'text-indigo-700', 'Devis envoyés encore valables'],
                        ['Acceptés', $row['accepted'], 'text-emerald-600', 'Convertis ou à convertir en facture'],
                        ['Expirés', $row['expired'], $row['expired_count'] ? 'text-red-600' : 'text-gray-900', $row['expired_count'].' devis à relancer ou clôturer'],
                    ] as [$label, $value, $color, $hint])
                        <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-5">
                            <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
                            <p class="mt-2 text-xl font-semibold sm:text-2xl {{ $color }}">{{ $money($value) }} <span class="text-sm font-medium text-gray-400">{{ $currency }}</span></p>
                            <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
                        </article>
                    @endforeach
                </div>
            @endforeach

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                {{-- Onglets de statut et recherche --}}
                <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                    <nav class="-mb-px flex gap-1 overflow-x-auto">
                        @foreach ($tabs as $value => [$label, $count])
                            <a href="{{ route('quotes.index', array_filter(['status' => $value, 'search' => $search])) }}"
                               class="inline-flex shrink-0 items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ (string) $status === (string) $value ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                {{ $label }}
                                <span class="rounded-full px-2 py-0.5 text-xs {{ (string) $status === (string) $value ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">{{ $count }}</span>
                            </a>
                        @endforeach
                    </nav>
                    <form method="GET" class="flex gap-2">
                        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                        <div class="relative flex-1 lg:w-72">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m21 21-4.3-4.3M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z"/></svg>
                            <input name="search" value="{{ $search }}" placeholder="Numéro ou client" class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Rechercher</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3">Devis</th>
                                <th class="px-6 py-3">Client</th>
                                <th class="px-6 py-3">Validité</th>
                                <th class="px-6 py-3">Statut</th>
                                <th class="px-6 py-3 text-right">Total TTC</th>
                                <th class="px-6 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($quotes as $quote)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <a href="{{ route('quotes.show', $quote) }}" class="font-semibold text-indigo-600 hover:text-indigo-500" wire:navigate>{{ $quote->number }}</a>
                                        <p class="text-xs text-gray-500">du {{ $quote->issue_date->format('d/m/Y') }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $quote->party->name }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <span class="{{ $quote->isExpired() ? 'font-semibold text-red-600' : 'text-gray-600' }}">{{ $quote->valid_until->format('d/m/Y') }}</span>
                                        @if ($quote->isExpired())<p class="text-xs text-red-500">expiré depuis {{ (int) $quote->valid_until->diffInDays(today()) }} j</p>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">@include('quotes._status')</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ $money($quote->total) }} <span class="text-xs font-normal text-gray-400">{{ $quote->currency }}</span></td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        @if ($quote->status === QuoteStatus::Draft)
                                            @can(Permission::QuotesCreate->value)
                                                <a href="{{ route('quotes.edit', $quote) }}" class="rounded-md px-2.5 py-1.5 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-white" wire:navigate>Modifier</a>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-16 text-center">
                                        <p class="font-medium text-gray-900">Aucun devis</p>
                                        <p class="mt-1 text-sm text-gray-500">{{ $search || $status ? 'Aucun résultat pour ces critères.' : 'Créez votre premier devis pour démarrer le cycle de vente.' }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($quotes->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $quotes->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-app-layout>
