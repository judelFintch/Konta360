@php
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $invoice = $creditNote->invoice;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <a href="{{ route('credit-notes.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500" wire:navigate>← Avoirs</a>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $creditNote->number }}</h1>
                    <x-badge color="amber">{{ $creditNote->status->label() }}</x-badge>
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ $creditNote->party->name }} · émis le {{ $creditNote->issue_date->format('d/m/Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('credit-notes.print', $creditNote) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6v-7Z"/></svg>
                    Aperçu / Imprimer
                </a>
                <a href="{{ route('credit-notes.pdf', $creditNote) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0-4-4m4 4 4-4M4 20h16"/></svg>
                    PDF
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    @include('documents._document-card', [
                        'document' => $creditNote,
                        'totalLabel' => 'Total crédité TTC',
                        'notesLabel' => 'Motif de l’avoir',
                        'notes' => $creditNote->reason,
                        'meta' => [
                            ['Date d’émission', $creditNote->issue_date->format('d/m/Y'), null, false],
                            ['Facture d’origine', $invoice->number, route('invoices.show', $invoice), false],
                            ['Date de la facture', $invoice->issue_date->format('d/m/Y'), null, false],
                            ['Devise', $creditNote->currency, null, false],
                        ],
                    ])
                </div>

                <aside class="space-y-6">
                    {{-- Impact sur la facture d’origine --}}
                    <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <p class="text-sm font-medium text-gray-500">Montant crédité</p>
                        <p class="mt-1 text-3xl font-semibold text-amber-700">{{ $money($creditNote->total) }} <span class="text-base font-medium text-gray-400">{{ $creditNote->currency }}</span></p>
                        <p class="mt-1 text-sm text-gray-500">Déduit du solde de la facture {{ $invoice->number }}.</p>

                        <dl class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Total facture TTC</dt><dd class="font-medium text-gray-900">{{ $money($invoice->total) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Total des avoirs</dt><dd class="font-medium text-amber-700">− {{ $money($invoice->creditedAmount()) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Règlements reçus</dt><dd class="font-medium text-emerald-700">− {{ $money($invoice->paidAmount()) }}</dd></div>
                            <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 font-semibold"><dt class="text-gray-700">Reste dû sur la facture</dt><dd class="text-gray-900">{{ $money($invoice->balanceDue()) }} {{ $invoice->currency }}</dd></div>
                        </dl>
                        @if ($invoice->paidAmount() + $invoice->creditedAmount() > (float) $invoice->total + 0.001)
                            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                Règlements et avoirs dépassent le total de la facture de {{ $money($invoice->paidAmount() + $invoice->creditedAmount() - (float) $invoice->total) }} {{ $invoice->currency }} : ce montant est dû au client (remboursement ou imputation).
                            </p>
                        @endif
                        <a href="{{ route('invoices.show', $invoice) }}" class="mt-4 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50" wire:navigate>Ouvrir la facture d’origine</a>
                    </article>

                    @include('documents._party-card', ['party' => $creditNote->party])

                    @include('documents._control-card')

                    <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Suivi</p>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-gray-500">Émis le</dt><dd class="text-gray-900">{{ $creditNote->created_at->format('d/m/Y à H:i') }}</dd></div>
                            @if ($creditNote->creator)<div class="flex justify-between gap-4"><dt class="text-gray-500">Par</dt><dd class="text-right text-gray-900">{{ $creditNote->creator->name }}</dd></div>@endif
                            <div class="flex justify-between gap-4"><dt class="text-gray-500">Comptabilisation</dt><dd class="text-gray-900">Automatique</dd></div>
                        </dl>
                    </article>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
