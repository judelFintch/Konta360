{{--
    Document commercial à l’écran : bandeau d’informations, lignes, totaux et notes.
    Paramètres : $document, $meta (liste de [libellé, valeur, url|null, alerte bool]),
    $totalLabel (facultatif), $notesLabel et $notes (facultatifs).
--}}
@php
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $notes ??= $document->notes;
@endphp

<article class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
    <div class="grid grid-cols-2 gap-6 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-white px-6 py-5 sm:grid-cols-4">
        @foreach ($meta as [$label, $value, $url, $alert])
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p>
                @if ($url)
                    <a href="{{ $url }}" class="mt-1 block font-semibold text-indigo-600 hover:text-indigo-500" wire:navigate>{{ $value }}</a>
                @else
                    <p class="mt-1 font-semibold {{ $alert ? 'text-red-600' : 'text-gray-900' }}">{{ $value }}</p>
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
                @foreach ($document->lines as $line)
                    <tr>
                        <td class="px-6 py-4">
                            <p class="font-medium text-gray-900">{{ $line->description }}</p>
                            <p class="text-xs text-gray-500">Réf. {{ $line->sku }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-right">{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', ' '), '0'), ',') }} <span class="text-xs text-gray-500">{{ \App\Modules\Catalog\Enums\Unit::display($line->unit) }}</span></td>
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
            <div class="flex justify-between"><dt class="text-gray-500">Total brut HT</dt><dd class="text-gray-900">{{ $money($document->subtotal) }}</dd></div>
            @if ((float) $document->discount_total > 0)
                <div class="flex justify-between"><dt class="text-gray-500">Remises</dt><dd class="text-gray-900">− {{ $money($document->discount_total) }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-gray-500">Total net HT</dt><dd class="text-gray-900">{{ $money((float) $document->subtotal - (float) $document->discount_total) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">TVA</dt><dd class="text-gray-900">{{ $money($document->tax_total) }}</dd></div>
            <div class="flex justify-between rounded-lg bg-indigo-600 px-4 py-3 text-base font-semibold text-white">
                <dt>{{ $totalLabel ?? 'Total TTC' }}</dt><dd>{{ $money($document->total) }} {{ $document->currency }}</dd>
            </div>
        </dl>
    </div>

    @if ($notes)
        <div class="border-t border-gray-100 px-6 py-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $notesLabel ?? 'Notes' }}</p>
            <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $notes }}</p>
        </div>
    @endif
</article>
