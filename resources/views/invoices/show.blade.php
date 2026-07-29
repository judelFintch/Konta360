@php use App\Modules\Administration\Enums\Permission; use App\Modules\Invoices\Enums\InvoiceStatus; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm font-medium text-indigo-600">Facture</p><h1 class="text-2xl font-semibold text-gray-900">{{ $invoice->number ?: 'Brouillon #'.$invoice->id }}</h1></div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Imprimer</a>
                <a href="{{ route('invoices.pdf', $invoice) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">PDF</a>
                @if ($invoice->status === InvoiceStatus::Draft)
                    @can(Permission::InvoicesUpdateDraft->value)<a href="{{ route('invoices.edit', $invoice) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Modifier</a>@endcan
                    @can(Permission::InvoicesValidate->value)
                        <form method="POST" action="{{ route('invoices.validate', $invoice) }}">@csrf @method('PATCH')<button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Valider la facture</button></form>
                    @endcan
                @endif
                @if ($invoice->status !== InvoiceStatus::Cancelled)
                    @can(Permission::InvoicesCancel->value)
                        <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" onsubmit="return confirm('Confirmer l’annulation de cette facture ?')">@csrf @method('PATCH')<button class="rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">Annuler</button></form>
                    @endcan
                @endif
                @if ($invoice->status === InvoiceStatus::Validated && $invoice->balanceDue() > 0)
                    @can(Permission::PaymentsRecord->value)
                        <a href="{{ route('payments.create', $invoice) }}" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500">Enregistrer un règlement</a>
                    @endcan
                @endif
                @if ($invoice->status === InvoiceStatus::Validated && $invoice->creditedAmount() < (float) $invoice->total)
                    @can(Permission::CreditNotesCreate->value)
                        <a href="{{ route('credit-notes.create', $invoice) }}" class="rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-400">Créer un avoir</a>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10"><div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        @if (session('success'))<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-10">
            <div class="grid gap-6 border-b border-gray-200 pb-8 sm:grid-cols-2">
                <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Facturé à</p><p class="mt-2 text-lg font-semibold text-gray-900">{{ $invoice->party->name }}</p><p class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ $invoice->party->address }}</p></div>
                <dl class="grid grid-cols-2 gap-4 text-sm sm:text-right">
                    <div><dt class="text-gray-500">Statut</dt><dd class="mt-1 font-semibold">{{ $invoice->status->label() }}</dd></div>
                    <div><dt class="text-gray-500">Devise</dt><dd class="mt-1 font-semibold">{{ $invoice->currency }}</dd></div>
                    <div><dt class="text-gray-500">Date</dt><dd class="mt-1 font-semibold">{{ $invoice->issue_date->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-gray-500">Échéance</dt><dd class="mt-1 font-semibold">{{ $invoice->due_date->format('d/m/Y') }}</dd></div>
                </dl>
            </div>
            @if ($invoice->quote)<p class="mt-5 text-sm text-gray-500">Issue du devis <a class="font-semibold text-indigo-600" href="{{ route('quotes.show', $invoice->quote) }}">{{ $invoice->quote->number }}</a></p>@endif
            <div class="mt-6 overflow-x-auto"><table class="min-w-full">
                <thead><tr class="border-b text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><th class="py-3">Article</th><th class="py-3 text-right">Qté</th><th class="py-3 text-right">Prix HT</th><th class="py-3 text-right">Remise</th><th class="py-3 text-right">Taxe</th><th class="py-3 text-right">Total TTC</th></tr></thead>
                <tbody class="divide-y divide-gray-100">@foreach ($invoice->lines as $line)<tr class="text-sm">
                    <td class="py-4"><p class="font-medium text-gray-900">{{ $line->description }}</p><p class="text-gray-500">{{ $line->sku }} · {{ $line->unit }}</p></td>
                    <td class="py-4 text-right">{{ number_format((float) $line->quantity, 3, ',', ' ') }}</td><td class="py-4 text-right">{{ number_format((float) $line->unit_price, 2, ',', ' ') }}</td>
                    <td class="py-4 text-right">{{ number_format((float) $line->discount_rate, 2, ',', ' ') }} %</td><td class="py-4 text-right">{{ number_format((float) $line->tax_amount, 2, ',', ' ') }}</td><td class="py-4 text-right font-semibold">{{ number_format((float) $line->total, 2, ',', ' ') }}</td>
                </tr>@endforeach</tbody>
            </table></div>
            <div class="mt-8 flex justify-end"><dl class="w-full max-w-sm space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Sous-total HT</dt><dd>{{ number_format((float) $invoice->subtotal, 2, ',', ' ') }} {{ $invoice->currency }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Remises</dt><dd>− {{ number_format((float) $invoice->discount_total, 2, ',', ' ') }} {{ $invoice->currency }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Taxes</dt><dd>{{ number_format((float) $invoice->tax_total, 2, ',', ' ') }} {{ $invoice->currency }}</dd></div>
                <div class="flex justify-between border-t pt-4 text-lg font-semibold"><dt>Total TTC</dt><dd>{{ number_format((float) $invoice->total, 2, ',', ' ') }} {{ $invoice->currency }}</dd></div>
                <div class="flex justify-between text-emerald-700"><dt>Montant payé</dt><dd>− {{ number_format($invoice->paidAmount(), 2, ',', ' ') }} {{ $invoice->currency }}</dd></div>
                <div class="flex justify-between text-amber-700"><dt>Avoirs émis</dt><dd>− {{ number_format($invoice->creditedAmount(), 2, ',', ' ') }} {{ $invoice->currency }}</dd></div>
                <div class="flex justify-between rounded-lg bg-indigo-50 px-3 py-2 text-base font-semibold text-indigo-900"><dt>Solde restant</dt><dd>{{ number_format($invoice->balanceDue(), 2, ',', ' ') }} {{ $invoice->currency }}</dd></div>
            </dl></div>
            <section class="mt-10 border-t border-gray-200 pt-8">
                <div class="flex items-center justify-between">
                    <div><h2 class="font-semibold text-gray-900">Historique des règlements</h2><p class="mt-1 text-sm text-gray-500">{{ $invoice->paymentLabel() }}</p></div>
                    <a href="{{ route('payments.index') }}" class="text-sm font-semibold text-indigo-600">Voir tous les règlements</a>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-3">Numéro</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3 text-right">Montant</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($invoice->payments as $payment)
                                <tr><td class="px-4 py-3 font-medium">{{ $payment->number }}</td><td class="px-4 py-3">{{ $payment->payment_date->format('d/m/Y') }}</td><td class="px-4 py-3">{{ $payment->method->label() }}</td><td class="px-4 py-3">{{ $payment->status->label() }}</td><td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun règlement enregistré.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="mt-10 border-t border-gray-200 pt-8">
                <div class="flex items-center justify-between">
                    <div><h2 class="font-semibold text-gray-900">Avoirs liés</h2><p class="mt-1 text-sm text-gray-500">Les montants crédités réduisent le solde de la facture.</p></div>
                    <a href="{{ route('credit-notes.index') }}" class="text-sm font-semibold text-indigo-600">Voir tous les avoirs</a>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-3">Numéro</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Motif</th><th class="px-4 py-3 text-right">Montant</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($invoice->creditNotes as $creditNote)
                                <tr><td class="px-4 py-3"><a href="{{ route('credit-notes.show', $creditNote) }}" class="font-semibold text-indigo-600">{{ $creditNote->number }}</a></td><td class="px-4 py-3">{{ $creditNote->issue_date->format('d/m/Y') }}</td><td class="px-4 py-3">{{ $creditNote->reason }}</td><td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $creditNote->total, 2, ',', ' ') }} {{ $creditNote->currency }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Aucun avoir émis.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            @if ($invoice->notes)<div class="mt-8 rounded-lg bg-gray-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Notes</p><p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $invoice->notes }}</p></div>@endif
        </article>
    </div></div>
</x-app-layout>
