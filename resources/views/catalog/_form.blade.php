@php
    use App\Modules\Catalog\Enums\ItemType;
    use App\Modules\Catalog\Enums\Unit;
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div>
        <x-input-label for="name" value="Désignation *" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $catalogItem?->name)" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="sku" value="Référence *" />
        <x-text-input id="sku" name="sku" class="mt-1 block w-full uppercase" :value="old('sku', $catalogItem?->sku)" required />
        <x-input-error :messages="$errors->get('sku')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="type" value="Type *" />
        <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (ItemType::cases() as $itemType)
                <option value="{{ $itemType->value }}" @selected(old('type', $catalogItem?->type?->value) === $itemType->value)>{{ $itemType->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('type')" class="mt-2" />
    </div>

    @php
        $currentUnit = old('unit', $catalogItem?->unit ?? Unit::Unit->value);
        $isStandardUnit = Unit::tryFrom((string) $currentUnit) !== null;
    @endphp
    <div x-data="{ unit: @js($isStandardUnit ? $currentUnit : Unit::OTHER) }">
        <x-input-label for="unit" value="Unité *" />
        <select id="unit" name="unit" x-model="unit" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (Unit::cases() as $unit)
                <option value="{{ $unit->value }}" @selected($isStandardUnit && $currentUnit === $unit->value)>{{ $unit->label() }}</option>
            @endforeach
            <option value="{{ Unit::OTHER }}" @selected(! $isStandardUnit)>Autre…</option>
        </select>
        <div x-show="unit === @js(Unit::OTHER)" @style(['display: none' => $isStandardUnit]) class="mt-2">
            <x-text-input id="unit_other" name="unit_other" maxlength="30" class="block w-full" placeholder="Précisez l’unité" :value="old('unit_other', $isStandardUnit ? '' : $currentUnit)" x-bind:required="unit === @js(Unit::OTHER)" />
            <p class="mt-1 text-xs text-gray-500">Une unité hors liste s’imprime telle quelle, sans traduction.</p>
        </div>
        <x-input-error :messages="$errors->get('unit')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="unit_price" value="Prix unitaire hors taxe *" />
        <x-text-input id="unit_price" name="unit_price" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('unit_price', $catalogItem?->unit_price)" required />
        <x-input-error :messages="$errors->get('unit_price')" class="mt-2" />
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="currency" value="Devise *" />
            <select id="currency" name="currency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach (['CDF', 'USD'] as $currency)
                    <option value="{{ $currency }}" @selected(old('currency', $catalogItem?->currency ?? \App\Models\CompanySetting::current()->default_currency) === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="tax_rate" value="Taxe (%) *" />
            <x-text-input id="tax_rate" name="tax_rate" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full" :value="old('tax_rate', $catalogItem?->tax_rate ?? \App\Models\CompanySetting::current()->default_tax_rate)" required />
            <x-input-error :messages="$errors->get('tax_rate')" class="mt-2" />
        </div>
    </div>

    <div class="md:col-span-2">
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $catalogItem?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="flex items-center md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input id="is_active" name="is_active" type="checkbox" value="1"
               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
               @checked(old('is_active', $catalogItem?->is_active ?? true))>
        <label for="is_active" class="ms-2 text-sm text-gray-700">Article actif et disponible à la facturation</label>
    </div>
</div>

<div class="mt-8 flex items-center justify-end gap-3">
    <a href="{{ route('catalog.index') }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">Annuler</a>
    <x-primary-button>{{ $submitLabel }}</x-primary-button>
</div>
