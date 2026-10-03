@php
    use App\Modules\Administration\Enums\Permission;
    use App\Modules\Documents\Enums\DocumentLanguage;
    use App\Modules\Invoices\Enums\InvoiceStatus;

    $money = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $printLanguage = $invoice->language ?? DocumentLanguage::French;
    $otherLanguage = $printLanguage === DocumentLanguage::French ? DocumentLanguage::English : DocumentLanguage::French;
    $isDraft = $invoice->status === InvoiceStatus::Draft;
    $isValidated = $invoice->status === InvoiceStatus::Validated;
    $isCancelled = $invoice->status === InvoiceStatus::Cancelled;
    $paid = $invoice->paidAmount();
    $credited = $invoice->creditedAmount();
    $offset = $invoice->offsetAmount();
    $balance = $invoice->balanceDue();
    $settledPercent = (float) $invoice->total > 0 ? min(100, round(($paid + $credited + $offset) / (float) $invoice->total * 100)) : 0;

    $timeline = collect([
        ['date' => $invoice->created_at, 'rank' => 0, 'label' => 'Facture créée', 'detail' => $invoice->creator?->name, 'color' => 'bg-gray-400'],
        $invoice->validated_at ? ['date' => $invoice->validated_at, 'rank' => 1, 'label' => 'Facture validée', 'detail' => $validator?->name, 'color' => 'bg-indigo-500'] : null,
        $invoice->cancelled_at ? ['date' => $invoice->cancelled_at, 'rank' => 4, 'label' => 'Facture annulée', 'detail' => $canceller?->name, 'color' => 'bg-red-500'] : null,
    ])->filter()
        ->merge($invoice->payments->map(fn ($payment) => [
            'date' => $payment->payment_date,
            'rank' => 2,
            'label' => ($payment->status->value === 'reversed' ? 'Règlement contrepassé · ' : 'Règlement reçu · ').$money($payment->amount).' '.$payment->currency,
            'detail' => $payment->number.' · '.$payment->method->label(),
            'color' => $payment->status->value === 'reversed' ? 'bg-gray-300' : 'bg-emerald-500',
        ]))
        ->merge($invoice->creditNotes->map(fn ($note) => [
            'date' => $note->issue_date,
            'rank' => 3,
            'label' => 'Avoir émis · '.$money($note->total).' '.$note->currency,
            'detail' => $note->number,
            'color' => 'bg-amber-500',
        ]))
        // Chronological order by day; within a day, the business sequence.
        ->sortBy(fn (array $event) => $event['date']->format('Y-m-d').'-'.$event['rank'])
        ->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <a href="{{ route('invoices.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500" wire:navigate>← Factures</a>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $invoice->number ?: 'Brouillon #'.$invoice->id }}</h1>
                    @include('invoices._status')
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ $invoice->party->name }} · émise le {{ $invoice->issue_date->format('d/m/Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6v-7Z"/></svg>
                    Aperçu / Imprimer
                </a>
                <a href="{{ route('invoices.pdf', $invoice) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0-4-4m4 4 4-4M4 20h16"/></svg>
                    PDF {{ strtoupper($printLanguage->value) }}
                </a>
                {{-- La même facture dans l’autre langue : montants et code de contrôle identiques. --}}
                <span class="inline-flex items-center rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700">
                    <a href="{{ route('invoices.print', [$invoice, 'lang' => $otherLanguage->value]) }}" target="_blank" class="px-3 py-2.5 hover:bg-gray-50" title="Aperçu en {{ strtolower($otherLanguage->label()) }}">Aperçu {{ strtoupper($otherLanguage->value) }}</a>
                    <a href="{{ route('invoices.pdf', [$invoice, 'lang' => $otherLanguage->value]) }}" class="border-l border-gray-300 px-3 py-2.5 hover:bg-gray-50" title="PDF en {{ strtolower($otherLanguage->label()) }}">PDF {{ strtoupper($otherLanguage->value) }}</a>
                </span>
                @if ($isDraft)
                    @can(Permission::InvoicesUpdateDraft->value)
                        <a href="{{ route('invoices.edit', $invoice) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Modifier</a>
                    @endcan
                @endif
                @unless ($isCancelled)
                    @can(Permission::InvoicesCancel->value)
                        @if ($cancelBlocker = $invoice->cancellationBlocker())
                            <span title="{{ $cancelBlocker }}" class="cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-400">Annuler la facture</span>
                        @else
                            <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" onsubmit="return confirm('{{ $isDraft ? 'Confirmer l’abandon de ce brouillon ?' : 'Annuler cette facture ? Son écriture comptable sera contrepassée.' }}')">
                                @csrf @method('PATCH')
                                <button class="rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">Annuler la facture</button>
                            </form>
                        @endif
                    @endcan
                @endunless
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif
            @if ($isCancelled)
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    Cette facture a été annulée{{ $invoice->cancelled_at ? ' le '.$invoice->cancelled_at->format('d/m/Y') : '' }}. Elle n’a plus de valeur et ne peut plus être encaissée.
                </div>
                @if ($paid > 0)
                    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <strong>Anomalie à régulariser :</strong> {{ $money($paid) }} {{ $invoice->currency }} de règlements restent actifs sur cette facture annulée.
                        Contrepassez-les (remboursement du client) ou faites vérifier la situation par le responsable comptable.
                    </div>
                @endif
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Document --}}
                <div class="space-y-6 lg:col-span-2">
                    @include('documents._document-card', [
                        'document' => $invoice,
                        'meta' => [
                            ['Date d’émission', $invoice->issue_date->format('d/m/Y'), null, false],
                            ['Échéance', $invoice->due_date->format('d/m/Y'), null, $invoice->isOverdue()],
                            ['Devise · langue', $invoice->currency.' · '.$printLanguage->label(), null, false],
                            ['Devis d’origine', $invoice->quote?->number ?? '—', $invoice->quote ? route('quotes.show', $invoice->quote) : null, false],
                        ],
                    ])

                    @if ($invoice->deductions->isNotEmpty())
                        {{-- Déductions --}}
                        <article class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                                <div>
                                    <h2 class="font-semibold text-gray-900">Déductions</h2>
                                    <p class="text-xs text-gray-500">Retranchées du total TTC ; la base de TVA est inchangée.</p>
                                </div>
                                <span class="text-sm text-gray-500">{{ $invoice->deductions->count() }}</span>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                @foreach ($invoice->deductions as $deduction)
                                    <li class="flex items-center justify-between gap-4 px-5 py-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900">{{ $deduction->description }}</p>
                                            <p class="text-xs text-gray-500">
                                                {{ $deduction->type->label() }}
                                                · {{ rtrim(rtrim(number_format((float) $deduction->quantity, 3, ',', ' '), '0'), ',') }} × {{ rtrim(rtrim(number_format((float) $deduction->unit_price, 4, ',', ' '), '0'), ',') }}
                                                @if ($deduction->received_on) · reçue le {{ $deduction->received_on->format('d/m/Y') }}@endif
                                                @if ($deduction->payment) · règlement {{ $deduction->payment->number }}@endif
                                            </p>
                                        </div>
                                        <p class="shrink-0 text-sm font-semibold text-gray-900">− {{ $money($deduction->amount) }}</p>
                                    </li>
                                @endforeach
                            </ul>
                            <dl class="space-y-1 border-t border-gray-100 px-5 py-4 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">Total TTC</dt><dd class="text-gray-900">{{ $money($invoice->total) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Total déductions</dt><dd class="text-gray-900">− {{ $money($invoice->deductions_total) }}</dd></div>
                                <div class="flex justify-between font-semibold"><dt class="text-gray-900">Net à payer</dt><dd class="text-indigo-700">{{ $money($invoice->netPayable()) }} {{ $invoice->currency }}</dd></div>
                            </dl>
                        </article>
                    @elseif ($isDraft)
                        {{-- Point d’entrée visible pour l’avance et les frais à déduire. --}}
                        <article class="flex flex-col gap-4 rounded-xl border-2 border-dashed border-indigo-200 bg-indigo-50/40 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="font-semibold text-gray-900">Déductions</h2>
                                <p class="mt-1 text-sm text-gray-600">Aucune déduction. Avance déjà reçue, frais d’opérateur ou de carburant payés par le client… : retranchez-les du total TTC pour obtenir le net à payer.</p>
                            </div>
                            @can(Permission::InvoicesUpdateDraft->value)
                                <a href="{{ route('invoices.edit', $invoice) }}#deductions" class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Ajouter des déductions</a>
                            @endcan
                        </article>
                    @endif

                    {{-- Règlements et avoirs --}}
                    <div class="grid gap-6 xl:grid-cols-2">
                        <article class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                                <h2 class="font-semibold text-gray-900">Règlements</h2>
                                <span class="text-sm text-gray-500">{{ $invoice->payments->count() }}</span>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                @forelse ($invoice->payments as $payment)
                                    <li class="flex items-center justify-between gap-4 px-5 py-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $payment->number }}</p>
                                            <p class="text-xs text-gray-500">{{ $payment->payment_date->format('d/m/Y') }} · {{ $payment->method->label() }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-semibold {{ $payment->status->value === 'reversed' ? 'text-gray-400 line-through' : 'text-emerald-700' }}">{{ $money($payment->amount) }} {{ $payment->currency }}</p>
                                            @if ($payment->status->value === 'reversed')<p class="text-xs text-gray-500">Contrepassé</p>@endif
                                        </div>
                                    </li>
                                @empty
                                    <li class="px-5 py-8 text-center text-sm text-gray-500">Aucun règlement enregistré.</li>
                                @endforelse
                            </ul>
                        </article>

                        <article class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                                <h2 class="font-semibold text-gray-900">Avoirs</h2>
                                <span class="text-sm text-gray-500">{{ $invoice->creditNotes->count() }}</span>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                @forelse ($invoice->creditNotes as $creditNote)
                                    <li>
                                        <a href="{{ route('credit-notes.show', $creditNote) }}" class="flex items-center justify-between gap-4 px-5 py-3 hover:bg-gray-50" wire:navigate>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-indigo-600">{{ $creditNote->number }}</p>
                                                <p class="truncate text-xs text-gray-500">{{ $creditNote->issue_date->format('d/m/Y') }} · {{ $creditNote->reason }}</p>
                                            </div>
                                            <p class="shrink-0 text-sm font-semibold text-amber-700">{{ $money($creditNote->total) }} {{ $creditNote->currency }}</p>
                                        </a>
                                    </li>
                                @empty
                                    <li class="px-5 py-8 text-center text-sm text-gray-500">Aucun avoir émis.</li>
                                @endforelse
                            </ul>
                        </article>
                    </div>
                </div>

                {{-- Panneau latéral --}}
                <aside class="space-y-6">
                    {{-- Situation et actions --}}
                    <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        @if ($isDraft)
                            <p class="text-sm font-medium text-gray-500">Brouillon · {{ $invoice->deductions->isNotEmpty() ? 'net à payer' : 'total TTC' }}</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $money($invoice->netPayable()) }} <span class="text-sm text-gray-400">{{ $invoice->currency }}</span></p>
                            <p class="mt-2 text-sm text-gray-500">Vérifiez les lignes puis validez : la facture recevra son numéro définitif et sera comptabilisée.</p>
                            @if ($invoice->deductions->contains(fn ($deduction) => $deduction->isAdvance()))
                                <p class="mt-2 text-sm text-gray-500">Les avances seront enregistrées comme règlements à la date de leur réception.</p>
                            @endif
                            @can(Permission::InvoicesValidate->value)
                                <form method="POST" action="{{ route('invoices.validate', $invoice) }}" class="mt-4">
                                    @csrf @method('PATCH')
                                    <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Valider la facture</button>
                                </form>
                            @endcan
                        @else
                            <p class="text-sm font-medium text-gray-500">Reste à payer</p>
                            <p class="mt-1 text-3xl font-semibold {{ $isCancelled ? 'text-gray-400 line-through' : ($balance > 0 ? ($invoice->isOverdue() ? 'text-red-600' : 'text-indigo-700') : 'text-emerald-600') }}">
                                {{ $money($balance) }} <span class="text-base font-medium text-gray-400">{{ $invoice->currency }}</span>
                            </p>
                            @if ($invoice->isOverdue())
                                <p class="mt-1 text-sm font-medium text-red-600">Échue depuis {{ (int) $invoice->due_date->diffInDays(today()) }} jour(s)</p>
                            @elseif ($isValidated && $balance > 0)
                                <p class="mt-1 text-sm text-gray-500">À régler avant le {{ $invoice->due_date->format('d/m/Y') }}</p>
                            @endif

                            <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $settledPercent }}%"></div>
                            </div>
                            <p class="mt-1 text-right text-xs text-gray-500">{{ $settledPercent }} % soldé</p>

                            <dl class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">Total TTC</dt><dd class="font-medium text-gray-900">{{ $money($invoice->total) }}</dd></div>
                                @if ($offset > 0)
                                    <div class="flex justify-between"><dt class="text-gray-500">Frais déduits</dt><dd class="font-medium text-gray-700">− {{ $money($offset) }}</dd></div>
                                @endif
                                <div class="flex justify-between"><dt class="text-gray-500">Règlements reçus</dt><dd class="font-medium text-emerald-700">− {{ $money($paid) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Avoirs</dt><dd class="font-medium text-amber-700">− {{ $money($credited) }}</dd></div>
                            </dl>

                            @if ($isValidated)
                                <div class="mt-5 space-y-2">
                                    @if ($balance > 0)
                                        @can(Permission::PaymentsRecord->value)
                                            <a href="{{ route('payments.create', $invoice) }}" class="block w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">Enregistrer un règlement</a>
                                        @endcan
                                    @endif
                                    @if ($credited < (float) $invoice->total)
                                        @can(Permission::CreditNotesCreate->value)
                                            <a href="{{ route('credit-notes.create', $invoice) }}" class="block w-full rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-center text-sm font-semibold text-amber-800 hover:bg-amber-100">Créer un avoir</a>
                                        @endcan
                                    @endif
                                </div>
                            @endif
                        @endif
                    </article>

                    @include('documents._party-card', ['party' => $invoice->party])

                    @include('documents._control-card')

                    {{-- Historique --}}
                    <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Historique</p>
                        <ol class="mt-4 space-y-4">
                            @foreach ($timeline as $event)
                                <li class="relative flex gap-3">
                                    @unless ($loop->last)<span class="absolute left-[5px] top-4 h-full w-px bg-gray-200"></span>@endunless
                                    <span class="relative mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $event['color'] }}"></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900">{{ $event['label'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $event['date']->format('d/m/Y') }}@if ($event['detail']) · {{ $event['detail'] }}@endif</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </article>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
