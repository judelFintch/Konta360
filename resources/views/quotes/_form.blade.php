@php
    $initialLines = old('lines');
    if ($initialLines === null && isset($quote)) {
        $initialLines = $quote->lines->map(fn ($line) => [
            'catalog_item_id' => (string) $line->catalog_item_id,
            'quantity' => $line->quantity,
            'discount_rate' => $line->discount_rate,
        ])->values()->all();
    }
    $initialLines ??= [['catalog_item_id' => '', 'quantity' => 1, 'discount_rate' => 0]];
    $itemsForJs = $catalogItems->mapWithKeys(fn ($item) => [(string) $item->id => [
        'price' => (float) $item->unit_price,
        'tax' => (float) $item->tax_rate,
        'currency' => $item->currency,
    ]]);
@endphp

<div x-data='{
    lines: @json($initialLines),
    items: @json($itemsForJs),
    currency: @json(old('currency', $quote->currency ?? \App\Models\Company::current()->default_currency)),
    addLine() { this.lines.push({ catalog_item_id: "", quantity: 1, discount_rate: 0 }) },
    lineValues(line) {
        const item = this.items[line.catalog_item_id];
        if (!item) return { ht: 0, tax: 0, total: 0 };
        const ht = Number(line.quantity || 0) * item.price;
        const discounted = ht * (1 - Number(line.discount_rate || 0) / 100);
        const tax = discounted * item.tax / 100;
        return { ht, tax, total: discounted + tax };
    },
    sum(key) { return this.lines.reduce((sum, line) => sum + this.lineValues(line)[key], 0) },
    money(value) { return new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) }
}'>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <div class="md:col-span-2">
            <x-input-label for="party_id" value="Client *" />
            <select id="party_id" name="party_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Sélectionner un client</option>
                @foreach ($parties as $party)
                    <option value="{{ $party->id }}" @selected((string) old('party_id', $quote->party_id ?? '') === (string) $party->id)>{{ $party->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('party_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="issue_date" value="Date d’émission *" />
            <x-text-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full"
                          :value="old('issue_date', isset($quote) ? $quote->issue_date->format('Y-m-d') : now()->format('Y-m-d'))" required />
            <x-input-error :messages="$errors->get('issue_date')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="valid_until" value="Valable jusqu’au *" />
            <x-text-input id="valid_until" name="valid_until" type="date" class="mt-1 block w-full"
                          :value="old('valid_until', isset($quote) ? $quote->valid_until->format('Y-m-d') : now()->addDays(\App\Models\Company::current()->default_quote_validity_days)->format('Y-m-d'))" required />
            <x-input-error :messages="$errors->get('valid_until')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="currency" value="Devise *" />
            <select id="currency" name="currency" x-model="currency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="CDF">CDF</option>
                <option value="USD">USD</option>
            </select>
            <x-input-error :messages="$errors->get('currency')" class="mt-2" />
        </div>
    </div>

    <div class="mt-8">
        <div class="mb-3 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Lignes du devis</h2>
                <p class="text-sm text-gray-500">Les prix et taxes proviennent du catalogue.</p>
            </div>
            <button type="button" @click="addLine()" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                Ajouter une ligne
            </button>
        </div>

        <x-input-error :messages="$errors->get('lines')" class="mb-3" />

        <div class="space-y-3">
            <template x-for="(line, index) in lines" :key="index">
                <div class="grid gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 lg:grid-cols-[minmax(240px,1fr)_110px_110px_140px_40px]">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Article</label>
                        <select x-model="line.catalog_item_id" :name="`lines[${index}][catalog_item_id]`" required
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Sélectionner</option>
                            @foreach ($catalogItems as $item)
                                <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }} ({{ $item->currency }})</option>
                            @endforeach
                        </select>
                        <p x-show="line.catalog_item_id && items[line.catalog_item_id]?.currency !== currency" class="mt-1 text-xs text-red-600">
                            La devise de cet article ne correspond pas au devis.
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Quantité</label>
                        <input type="number" min="0.001" step="0.001" x-model.number="line.quantity" :name="`lines[${index}][quantity]`" required
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Remise %</label>
                        <input type="number" min="0" max="100" step="0.01" x-model.number="line.discount_rate" :name="`lines[${index}][discount_rate]`" required
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Total TTC</label>
                        <p class="mt-3 text-right text-sm font-semibold text-gray-900" x-text="`${money(lineValues(line).total)} ${currency}`"></p>
                    </div>
                    <button type="button" @click="lines.splice(index, 1)" x-show="lines.length > 1"
                            class="mt-5 h-9 rounded-md text-xl text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="Supprimer la ligne">×</button>
                </div>
            </template>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
        <div>
            <x-input-label for="notes" value="Notes et conditions" />
            <textarea id="notes" name="notes" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $quote->notes ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>
        <div class="rounded-xl bg-gray-900 p-5 text-white">
            <div class="flex justify-between text-sm text-gray-300"><span>Total HT brut</span><span x-text="`${money(sum('ht'))} ${currency}`"></span></div>
            <div class="mt-3 flex justify-between text-sm text-gray-300"><span>Taxes estimées</span><span x-text="`${money(sum('tax'))} ${currency}`"></span></div>
            <div class="mt-4 flex justify-between border-t border-gray-700 pt-4 text-lg font-semibold"><span>Total TTC</span><span x-text="`${money(sum('total'))} ${currency}`"></span></div>
        </div>
    </div>

    <div class="mt-8 flex items-center justify-end gap-3">
        <a href="{{ route('quotes.index') }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">Annuler</a>
        <x-primary-button>{{ $submitLabel }}</x-primary-button>
    </div>
</div>
