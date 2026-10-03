@php
    use App\Modules\Administration\Enums\Permission;
    use App\Modules\Quotes\Enums\QuoteStatus;

    $money = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $isDraft = $quote->status === QuoteStatus::Draft;
    $convertible = in_array($quote->status, [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Accepted], true);
    $daysLeft = (int) today()->diffInDays($quote->valid_until, false);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <a href="{{ route('quotes.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500" wire:navigate>← Devis</a>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $quote->number }}</h1>
                    @include('quotes._status')
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ $quote->party->name }} · émis le {{ $quote->issue_date->format('d/m/Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('quotes.print', $quote) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6v-7Z"/></svg>
                    Aperçu / Imprimer
                </a>
                <a href="{{ route('quotes.pdf', $quote) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0-4-4m4 4 4-4M4 20h16"/></svg>
                    PDF
                </a>
                @if ($isDraft)
                    @can(Permission::QuotesCreate->value)
                        <a href="{{ route('quotes.edit', $quote) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50" wire:navigate>Modifier</a>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if ($quote->isExpired())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    Ce devis a expiré le {{ $quote->valid_until->format('d/m/Y') }}. Relancez le client ou établissez un nouveau devis avec des conditions à jour.
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    @include('documents._document-card', [
                        'document' => $quote,
                        'meta' => [
                            ['Date d’émission', $quote->issue_date->format('d/m/Y'), null, false],
                            ['Valable jusqu’au', $quote->valid_until->format('d/m/Y'), null, $quote->isExpired()],
                            ['Devise', $quote->currency, null, false],
                            ['Facture', $quote->invoice ? ($quote->invoice->number ?: 'Brouillon #'.$quote->invoice->id) : '—', $quote->invoice ? route('invoices.show', $quote->invoice) : null, false],
                        ],
                    ])
                </div>

                <aside class="space-y-6">
                    {{-- Étape du cycle de vente et actions --}}
                    <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <p class="text-sm font-medium text-gray-500">Montant du devis</p>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $money($quote->total) }} <span class="text-base font-medium text-gray-400">{{ $quote->currency }}</span></p>
                        @if ($convertible && ! $quote->invoice)
                            <p class="mt-1 text-sm {{ $quote->isExpired() ? 'font-medium text-red-600' : 'text-gray-500' }}">
                                {{ $quote->isExpired() ? 'Expiré depuis '.abs($daysLeft).' jour(s)' : ($daysLeft === 0 ? 'Valable jusqu’à aujourd’hui' : 'Encore valable '.$daysLeft.' jour(s)') }}
                            </p>
                        @endif

                        {{-- Étapes : brouillon → envoyé → facturé --}}
                        @php
                            $steps = [
                                ['Brouillon', true],
                                ['Envoyé au client', $quote->status !== QuoteStatus::Draft],
                                ['Facturé', (bool) $quote->invoice],
                            ];
                        @endphp
                        <ol class="mt-5 space-y-3 border-t border-gray-100 pt-4">
                            @foreach ($steps as [$label, $done])
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $done ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-400' }}">
                                        @if ($done)
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                        @else
                                            {{ $loop->iteration }}
                                        @endif
                                    </span>
                                    <span class="{{ $done ? 'font-medium text-gray-900' : 'text-gray-500' }}">{{ $label }}</span>
                                </li>
                            @endforeach
                        </ol>

                        <div class="mt-5 space-y-2">
                            @if ($isDraft)
                                @can(Permission::QuotesCreate->value)
                                    <form method="POST" action="{{ route('quotes.send', $quote) }}">
                                        @csrf @method('PATCH')
                                        <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Marquer comme envoyé</button>
                                    </form>
                                @endcan
                            @endif
                            @if ($convertible)
                                @if ($quote->invoice)
                                    @can(Permission::InvoicesView->value)
                                        <a href="{{ route('invoices.show', $quote->invoice) }}" class="block w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-emerald-500" wire:navigate>Voir la facture</a>
                                    @endcan
                                @else
                                    @can(Permission::QuotesConvert->value)
                                        @can(Permission::InvoicesCreate->value)
                                            <form method="POST" action="{{ route('quotes.invoice', $quote) }}">
                                                @csrf
                                                <button class="w-full rounded-lg {{ $isDraft ? 'border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100' : 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-500' }} px-4 py-2.5 text-sm font-semibold"
                                                        onclick="return confirm('Le client a accepté ? Une facture brouillon sera créée à partir de ce devis.')">
                                                    Convertir en facture
                                                </button>
                                            </form>
                                        @endcan
                                    @endcan
                                @endif
                            @endif
                        </div>
                    </article>

                    @include('documents._party-card', ['party' => $quote->party])

                    @include('documents._control-card')

                    <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Suivi</p>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-gray-500">Créé le</dt><dd class="text-gray-900">{{ $quote->created_at->format('d/m/Y') }}</dd></div>
                            @if ($quote->creator)<div class="flex justify-between gap-4"><dt class="text-gray-500">Par</dt><dd class="text-right text-gray-900">{{ $quote->creator->name }}</dd></div>@endif
                            @if ($quote->invoice)<div class="flex justify-between gap-4"><dt class="text-gray-500">Facturé le</dt><dd class="text-gray-900">{{ $quote->invoice->created_at->format('d/m/Y') }}</dd></div>@endif
                        </dl>
                    </article>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
