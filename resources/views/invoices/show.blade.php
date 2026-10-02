@php
    use App\Modules\Administration\Enums\Permission;
    use App\Modules\Invoices\Enums\InvoiceStatus;

    $money = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $isDraft = $invoice->status === InvoiceStatus::Draft;
    $isValidated = $invoice->status === InvoiceStatus::Validated;
    $isCancelled = $invoice->status === InvoiceStatus::Cancelled;
    $paid = $invoice->paidAmount();
    $credited = $invoice->creditedAmount();
    $balance = $invoice->balanceDue();
    $settledPercent = (float) $invoice->total > 0 ? min(100, round(($paid + $credited) / (float) $invoice->total * 100)) : 0;
    $netTotal = (float) $invoice->subtotal - (float) $invoice->discount_total;

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
                    PDF
                </a>
                @if ($isDraft)
                    @can(Permission::InvoicesUpdateDraft->value)
                        <a href="{{ route('invoices.edit', $invoice) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Modifier</a>
                    @endcan
                @endif
                @unless ($isCancelled)
                    @can(Permission::InvoicesCancel->value)
                        <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" onsubmit="return confirm('Confirmer l’annulation de cette facture ?')">
                            @csrf @method('PATCH')
                            <button class="rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">Annuler la facture</button>
                        </form>
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
            @if ($isCancelled)
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    Cette facture a été annulée{{ $invoice->cancelled_at ? ' le '.$invoice->cancelled_at->format('d/m/Y') : '' }}. Elle n’a plus de valeur et ne peut plus être encaissée.
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Document --}}
                <div class="space-y-6 lg:col-span-2">
                    <article class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                        <div class="grid gap-6 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-white px-6 py-5 sm:grid-cols-4">
                            @foreach ([
                                ['Date d’émission', $invoice->issue_date->format('d/m/Y')],
                                ['Échéance', $invoice->due_date->format('d/m/Y')],
                                ['Devise', $invoice->currency],
                                ['Devis d’origine', $invoice->quote?->number ?? '—'],
                            ] as [$label, $value])
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p>
                                    @if ($loop->last && $invoice->quote)
                                        <a href="{{ route('quotes.show', $invoice->quote) }}" class="mt-1 block font-semibold text-indigo-600 hover:text-indigo-500" wire:navigate>{{ $value }}</a>
                                    @else
                                        <p class="mt-1 font-semibold {{ $loop->index === 1 && $invoice->isOverdue() ? 'text-red-600' : 'text-gray-900' }}">{{ $value }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <th class="px-6 py-3">Désignation</th>
                                        <th class="px-4 py-3 text-right">Qté</th>
                                        <th class="px-4 py-3 text-right">P.U. HT</th>
                                        <th class="px-4 py-3 text-right">Remise</th>
                                        <th class="px-4 py-3 text-right">TVA</th>
                                        <th class="px-6 py-3 text-right">Montant HT</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($invoice->lines as $line)
                                        <tr>
                                            <td class="px-6 py-4">
                                                <p class="font-medium text-gray-900">{{ $line->description }}</p>
                                                <p class="text-xs text-gray-500">Réf. {{ $line->sku }}</p>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right">{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', ' '), '0'), ',') }} <span class="text-xs text-gray-500">{{ $line->unit }}</span></td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right">{{ $money($line->unit_price) }}</td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right text-gray-500">{{ (float) $line->discount_rate > 0 ? $money($line->discount_rate).' %' : '—' }}</td>
                                            <td class="whitespace-nowrap px-4 py-4 text-right text-gray-500">{{ $money($line->tax_rate) }} %</td>
                                            <td class="whitespace-nowrap px-6 py-4 text-right font-semibold text-gray-900">{{ $money((float) $line->subtotal - (float) $line->discount_amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex justify-end border-t border-gray-100 px-6 py-5">
                            <dl class="w-full max-w-sm space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">Total brut HT</dt><dd class="text-gray-900">{{ $money($invoice->subtotal) }}</dd></div>
                                @if ((float) $invoice->discount_total > 0)
                                    <div class="flex justify-between"><dt class="text-gray-500">Remises</dt><dd class="text-gray-900">− {{ $money($invoice->discount_total) }}</dd></div>
                                @endif
                                <div class="flex justify-between"><dt class="text-gray-500">Total net HT</dt><dd class="text-gray-900">{{ $money($netTotal) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">TVA</dt><dd class="text-gray-900">{{ $money($invoice->tax_total) }}</dd></div>
                                <div class="flex justify-between rounded-lg bg-indigo-600 px-4 py-3 text-base font-semibold text-white">
                                    <dt>Total TTC</dt><dd>{{ $money($invoice->total) }} {{ $invoice->currency }}</dd>
                                </div>
                            </dl>
                        </div>

                        @if ($invoice->notes)
                            <div class="border-t border-gray-100 px-6 py-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Notes</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $invoice->notes }}</p>
                            </div>
                        @endif
                    </article>

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
                            <p class="text-sm font-medium text-gray-500">Brouillon</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $money($invoice->total) }} <span class="text-sm text-gray-400">{{ $invoice->currency }}</span></p>
                            <p class="mt-2 text-sm text-gray-500">Vérifiez les lignes puis validez : la facture recevra son numéro définitif et sera comptabilisée.</p>
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

                    {{-- Client --}}
                    <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Client</p>
                        <p class="mt-2 font-semibold text-gray-900">{{ $invoice->party->name }}</p>
                        <dl class="mt-2 space-y-1 text-sm text-gray-600">
                            @if ($invoice->party->tax_identifier)<div><span class="text-gray-400">NIF</span> {{ $invoice->party->tax_identifier }}</div>@endif
                            @if ($invoice->party->address)<div class="whitespace-pre-line">{{ $invoice->party->address }}</div>@endif
                            @if ($invoice->party->email)<div><a href="mailto:{{ $invoice->party->email }}" class="text-indigo-600 hover:text-indigo-500">{{ $invoice->party->email }}</a></div>@endif
                            @if ($invoice->party->phone)<div>{{ $invoice->party->phone }}</div>@endif
                        </dl>
                    </article>

                    {{-- Contrôle --}}
                    <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Contrôle du document</p>
                        @if ($fingerprint)
                            <p class="mt-2 font-mono text-base font-semibold tracking-wider text-gray-900">{{ $fingerprint }}</p>
                            <p class="mt-1 text-xs text-gray-500">Code imprimé sur la facture, avec un QR code de vérification.</p>
                            <a href="{{ $verificationUrl }}" target="_blank" class="mt-3 inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-500">Ouvrir la page de vérification →</a>
                        @else
                            <p class="mt-2 text-sm text-gray-500">Le code de contrôle et le QR code sont attribués à la validation.</p>
                        @endif
                    </article>

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
