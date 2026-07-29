@php use App\Modules\Administration\Enums\Permission; use App\Modules\Quotes\Enums\QuoteStatus; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">Devis</p>
                <h1 class="text-2xl font-semibold text-gray-900">{{ $quote->number }}</h1>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('quotes.print', $quote) }}" target="_blank" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Imprimer</a>
                <a href="{{ route('quotes.pdf', $quote) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">PDF</a>
                @if ($quote->status === QuoteStatus::Draft)
                    @can(Permission::QuotesCreate->value)
                        <a href="{{ route('quotes.edit', $quote) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Modifier</a>
                        <form method="POST" action="{{ route('quotes.send', $quote) }}">
                            @csrf @method('PATCH')
                            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Marquer comme envoyé</button>
                        </form>
                    @endcan
                @endif
                @if (in_array($quote->status, [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Accepted], true))
                    @if ($quote->invoice)
                        @can(Permission::InvoicesView->value)
                            <a href="{{ route('invoices.show', $quote->invoice) }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Voir la facture</a>
                        @endcan
                    @else
                        @can(Permission::QuotesConvert->value)
                            @can(Permission::InvoicesCreate->value)
                                <form method="POST" action="{{ route('quotes.invoice', $quote) }}">
                                    @csrf
                                    <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                                            onclick="return confirm('Créer une facture brouillon à partir de ce devis ?')">
                                        Convertir en facture
                                    </button>
                                </form>
                            @endcan
                        @endcan
                    @endif
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif

            <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-10">
                <div class="grid gap-6 border-b border-gray-200 pb-8 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Client</p>
                        <p class="mt-2 text-lg font-semibold text-gray-900">{{ $quote->party->name }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ $quote->party->address }}</p>
                    </div>
                    <dl class="grid grid-cols-2 gap-4 text-sm sm:text-right">
                        <div><dt class="text-gray-500">Statut</dt><dd class="mt-1 font-semibold text-gray-900">{{ $quote->status->label() }}</dd></div>
                        <div><dt class="text-gray-500">Devise</dt><dd class="mt-1 font-semibold text-gray-900">{{ $quote->currency }}</dd></div>
                        <div><dt class="text-gray-500">Émis le</dt><dd class="mt-1 font-semibold text-gray-900">{{ $quote->issue_date->format('d/m/Y') }}</dd></div>
                        <div><dt class="text-gray-500">Valable au</dt><dd class="mt-1 font-semibold text-gray-900">{{ $quote->valid_until->format('d/m/Y') }}</dd></div>
                    </dl>
                </div>

                <div class="mt-8 overflow-x-auto">
                    <table class="min-w-full">
                        <thead><tr class="border-b text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-3">Article</th><th class="py-3 text-right">Qté</th><th class="py-3 text-right">Prix HT</th>
                            <th class="py-3 text-right">Remise</th><th class="py-3 text-right">Taxe</th><th class="py-3 text-right">Total TTC</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($quote->lines as $line)
                                <tr class="text-sm">
                                    <td class="py-4"><p class="font-medium text-gray-900">{{ $line->description }}</p><p class="text-gray-500">{{ $line->sku }} · {{ $line->unit }}</p></td>
                                    <td class="py-4 text-right text-gray-700">{{ number_format((float) $line->quantity, 3, ',', ' ') }}</td>
                                    <td class="py-4 text-right text-gray-700">{{ number_format((float) $line->unit_price, 2, ',', ' ') }}</td>
                                    <td class="py-4 text-right text-gray-700">{{ number_format((float) $line->discount_rate, 2, ',', ' ') }} %</td>
                                    <td class="py-4 text-right text-gray-700">{{ number_format((float) $line->tax_amount, 2, ',', ' ') }}</td>
                                    <td class="py-4 text-right font-semibold text-gray-900">{{ number_format((float) $line->total, 2, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 flex justify-end">
                    <dl class="w-full max-w-sm space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Sous-total HT</dt><dd class="font-medium">{{ number_format((float) $quote->subtotal, 2, ',', ' ') }} {{ $quote->currency }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Remises</dt><dd class="font-medium">− {{ number_format((float) $quote->discount_total, 2, ',', ' ') }} {{ $quote->currency }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Taxes</dt><dd class="font-medium">{{ number_format((float) $quote->tax_total, 2, ',', ' ') }} {{ $quote->currency }}</dd></div>
                        <div class="flex justify-between border-t pt-4 text-lg font-semibold"><dt>Total TTC</dt><dd>{{ number_format((float) $quote->total, 2, ',', ' ') }} {{ $quote->currency }}</dd></div>
                    </dl>
                </div>
                @if ($quote->notes)<div class="mt-8 rounded-lg bg-gray-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Notes</p><p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $quote->notes }}</p></div>@endif
            </article>
        </div>
    </div>
</x-app-layout>
