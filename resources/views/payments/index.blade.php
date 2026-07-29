@php use App\Modules\Administration\Enums\Permission; use App\Modules\Payments\Enums\PaymentStatus; @endphp

<x-app-layout>
    <x-slot name="header">
        <div><p class="text-sm font-medium text-indigo-600">Trésorerie</p><h1 class="text-2xl font-semibold text-gray-900">Règlements clients</h1><p class="mt-1 text-sm text-gray-500">Historique des paiements reçus sur les factures, avec la possibilité d'annuler un règlement erroné.</p></div>
    </x-slot>

    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if (session('success'))<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <form method="GET" class="mb-6 flex gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <input name="search" value="{{ $search }}" placeholder="Règlement, facture, client ou référence" class="min-w-0 flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <x-secondary-button type="submit">Rechercher</x-secondary-button>
        </form>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-6 py-3">Règlement</th><th class="px-6 py-3">Facture / Client</th><th class="px-6 py-3">Mode</th><th class="px-6 py-3">Statut</th><th class="px-6 py-3 text-right">Montant</th><th class="px-6 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($payments as $payment)
                        <tr class="{{ $payment->status === PaymentStatus::Reversed ? 'bg-gray-50 text-gray-500' : '' }}">
                            <td class="px-6 py-4"><p class="font-semibold">{{ $payment->number }}</p><p class="text-sm text-gray-500">{{ $payment->payment_date->format('d/m/Y') }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</p></td>
                            <td class="px-6 py-4"><a href="{{ route('invoices.show', $payment->invoice) }}" class="font-semibold text-indigo-600">{{ $payment->invoice->number }}</a><p class="text-sm">{{ $payment->invoice->party->name }}</p></td>
                            <td class="px-6 py-4 text-sm">{{ $payment->method->label() }}</td>
                            <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $payment->status === PaymentStatus::Recorded ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">{{ $payment->status->label() }}</span></td>
                            <td class="px-6 py-4 text-right font-semibold">{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</td>
                            <td class="px-6 py-4 text-right">
                                @if ($payment->status === PaymentStatus::Recorded)
                                    @can(Permission::PaymentsReverse->value)
                                        <form method="POST" action="{{ route('payments.reverse', $payment) }}" x-data="{ open: false }">
                                            @csrf @method('PATCH')
                                            <button type="button" @click="open = !open" class="text-sm font-semibold text-red-600">Annuler</button>
                                            <div x-show="open" class="mt-2 flex min-w-72 gap-2">
                                                <input name="reversal_reason" required minlength="3" placeholder="Motif obligatoire" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                                                <button class="rounded-md bg-red-600 px-3 text-xs font-semibold text-white">Confirmer</button>
                                            </div>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-14 text-center"><p class="font-medium text-gray-900">Aucun règlement</p><p class="mt-1 text-sm text-gray-500">Les paiements enregistrés apparaîtront ici.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>@if ($payments->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $payments->links() }}</div>@endif</div>
    </div></div>
</x-app-layout>
