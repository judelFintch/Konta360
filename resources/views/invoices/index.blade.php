@php
    use App\Modules\Administration\Enums\Permission;
    use App\Modules\Invoices\Enums\InvoiceStatus;

    $money = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $tabs = ['' => ['Toutes', $counts->sum()]] + collect(InvoiceStatus::cases())
        ->mapWithKeys(fn (InvoiceStatus $case) => [$case->value => [$case === InvoiceStatus::Draft ? 'Brouillons' : $case->label().'s', $counts[$case->value] ?? 0]])
        ->all();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">Ventes</p>
                <h1 class="text-2xl font-semibold text-gray-900">Factures</h1>
                <p class="mt-1 text-sm text-gray-500">Suivez l’échéance de chaque facture et le montant restant à encaisser.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @can(Permission::ReportsExport->value)
                    <a href="{{ route('exports.invoices') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Exporter CSV</a>
                @endcan
                @can(Permission::QuotesCreate->value)
                    <a href="{{ route('quotes.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500" wire:navigate>+ Nouveau devis</a>
                @endcan
            </div>
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
                        ['Total facturé', $row['invoiced'], 'text-gray-900', 'Factures validées'],
                        ['Encaissé', $row['collected'], 'text-emerald-600', 'Règlements enregistrés'],
                        ['Reste à encaisser', $row['outstanding'], 'text-indigo-700', 'Après règlements et avoirs'],
                        ['En retard', $row['overdue_amount'], $row['overdue_count'] ? 'text-red-600' : 'text-gray-900', $row['overdue_count'].' facture(s) échue(s)'],
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
                            <a href="{{ route('invoices.index', array_filter(['status' => $value, 'search' => $search])) }}"
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
                                <th class="px-6 py-3">Facture</th>
                                <th class="px-6 py-3">Client</th>
                                <th class="px-6 py-3">Échéance</th>
                                <th class="px-6 py-3">Situation</th>
                                <th class="px-6 py-3 text-right">Total TTC</th>
                                <th class="px-6 py-3 text-right">Reste dû</th>
                                <th class="px-6 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($invoices as $invoice)
                                @php $validated = $invoice->status === InvoiceStatus::Validated; @endphp
                                <tr class="group hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="font-semibold text-indigo-600 hover:text-indigo-500" wire:navigate>{{ $invoice->number ?: 'Brouillon #'.$invoice->id }}</a>
                                        <p class="text-xs text-gray-500">du {{ $invoice->issue_date->format('d/m/Y') }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $invoice->party->name }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <span class="{{ $invoice->isOverdue() ? 'font-semibold text-red-600' : 'text-gray-600' }}">{{ $invoice->due_date->format('d/m/Y') }}</span>
                                        @if ($invoice->isOverdue())<p class="text-xs text-red-500">{{ (int) $invoice->due_date->diffInDays(today()) }} j de retard</p>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">@include('invoices._status')</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ $money($invoice->total) }} <span class="text-xs font-normal text-gray-400">{{ $invoice->currency }}</span></td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold {{ $validated && $invoice->balanceDue() > 0 ? 'text-indigo-700' : 'text-gray-400' }}">
                                        {{ $validated ? $money($invoice->balanceDue()) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($validated && $invoice->balanceDue() > 0)
                                                @can(Permission::PaymentsRecord->value)
                                                    <a href="{{ route('payments.create', $invoice) }}" class="rounded-md bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 hover:bg-emerald-100">Encaisser</a>
                                                @endcan
                                            @endif
                                            @if ($validated && $invoice->creditedAmount() < (float) $invoice->total)
                                                @can(Permission::CreditNotesCreate->value)
                                                    <a href="{{ route('credit-notes.create', $invoice) }}" class="rounded-md bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20 hover:bg-amber-100">Créer un avoir</a>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center">
                                        <p class="font-medium text-gray-900">Aucune facture</p>
                                        <p class="mt-1 text-sm text-gray-500">{{ $search || $status ? 'Aucun résultat pour ces critères.' : 'Convertissez un devis accepté pour créer votre première facture.' }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($invoices->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $invoices->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-app-layout>
