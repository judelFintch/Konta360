@php use App\Modules\Administration\Enums\Permission; use App\Modules\Invoices\Enums\InvoiceStatus; @endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">Ventes</p>
            <h1 class="text-2xl font-semibold text-gray-900">Factures</h1>
            <p class="mt-1 text-sm text-gray-500">Suivez les factures issues des devis, leur échéance de paiement et le solde restant dû.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
            <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[1fr_220px_auto]">
                <input name="search" value="{{ $search }}" placeholder="Numéro ou client" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tous les statuts</option>
                    @foreach (InvoiceStatus::cases() as $invoiceStatus)
                        <option value="{{ $invoiceStatus->value }}" @selected($status === $invoiceStatus->value)>{{ $invoiceStatus->label() }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">Filtrer</x-secondary-button>
            </form>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-6 py-3">Numéro</th><th class="px-6 py-3">Client</th><th class="px-6 py-3">Échéance</th>
                            <th class="px-6 py-3">Statut</th><th class="px-6 py-3 text-right">Total TTC</th><th class="px-6 py-3 text-right">Actions</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($invoices as $invoice)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><a href="{{ route('invoices.show', $invoice) }}" class="font-semibold text-indigo-600 hover:text-indigo-500">{{ $invoice->number ?: 'Brouillon #'.$invoice->id }}</a></td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $invoice->party->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $invoice->due_date->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4"><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ $invoice->status->label() }}</span></td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ number_format((float) $invoice->total, 2, ',', ' ') }} {{ $invoice->currency }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-3">
                                            <a href="{{ route('invoices.show', $invoice) }}" class="text-sm font-semibold text-indigo-600">Voir</a>
                                            @if ($invoice->status === InvoiceStatus::Validated && $invoice->creditedAmount() < (float) $invoice->total)
                                                @can(Permission::CreditNotesCreate->value)
                                                    <a href="{{ route('credit-notes.create', $invoice) }}" class="rounded-md bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-400">Créer un avoir</a>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-14 text-center"><p class="font-medium text-gray-900">Aucune facture</p><p class="mt-1 text-sm text-gray-500">Convertissez un devis envoyé pour créer une facture.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($invoices->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $invoices->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-app-layout>
