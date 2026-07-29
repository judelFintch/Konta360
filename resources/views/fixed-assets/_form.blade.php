@php use App\Modules\FixedAssets\Enums\AssetCategory; use App\Modules\FixedAssets\Enums\AssetStatus; @endphp

<div class="grid gap-6 md:grid-cols-2">
    <div>
        <x-input-label for="name" value="Désignation *" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $asset?->name)" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="category" value="Catégorie *" />
        <select id="category" name="category" class="mt-1 block w-full rounded-md border-gray-300" required>
            @foreach (AssetCategory::cases() as $category)<option value="{{ $category->value }}" @selected(old('category', $asset?->category?->value) === $category->value)>{{ $category->label() }}</option>@endforeach
        </select>
        <x-input-error :messages="$errors->get('category')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="acquisition_date" value="Date d’acquisition *" />
        <x-text-input id="acquisition_date" name="acquisition_date" type="date" class="mt-1 block w-full" :value="old('acquisition_date', $asset?->acquisition_date?->format('Y-m-d'))" required />
        <x-input-error :messages="$errors->get('acquisition_date')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="in_service_date" value="Date de mise en service *" />
        <x-text-input id="in_service_date" name="in_service_date" type="date" class="mt-1 block w-full" :value="old('in_service_date', $asset?->in_service_date?->format('Y-m-d'))" required />
        <x-input-error :messages="$errors->get('in_service_date')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="acquisition_cost" value="Coût d’acquisition *" />
        <x-text-input id="acquisition_cost" name="acquisition_cost" type="number" min="0.01" step="0.01" class="mt-1 block w-full" :value="old('acquisition_cost', $asset?->acquisition_cost)" required />
        <x-input-error :messages="$errors->get('acquisition_cost')" class="mt-2" />
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="residual_value" value="Valeur résiduelle *" />
            <x-text-input id="residual_value" name="residual_value" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('residual_value', $asset?->residual_value ?? 0)" required />
            <x-input-error :messages="$errors->get('residual_value')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="currency" value="Devise *" />
            <select id="currency" name="currency" class="mt-1 block w-full rounded-md border-gray-300"><option value="CDF" @selected(old('currency', $asset?->currency ?? 'CDF') === 'CDF')>CDF</option><option value="USD" @selected(old('currency', $asset?->currency ?? 'CDF') === 'USD')>USD</option></select>
        </div>
    </div>
    <div>
        <x-input-label for="useful_life_months" value="Durée d’utilisation (mois) *" />
        <x-text-input id="useful_life_months" name="useful_life_months" type="number" min="1" max="1200" class="mt-1 block w-full" :value="old('useful_life_months', $asset?->useful_life_months ?? 60)" required />
        <x-input-error :messages="$errors->get('useful_life_months')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="supplier_id" value="Fournisseur" />
        <select id="supplier_id" name="supplier_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Aucun fournisseur</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $asset?->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}</option>@endforeach</select>
        <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
    </div>
    @if ($asset)
        <div>
            <x-input-label for="status" value="Statut *" />
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300">@foreach (AssetStatus::cases() as $status)<option value="{{ $status->value }}" @selected(old('status', $asset->status->value) === $status->value)>{{ $status->label() }}</option>@endforeach</select>
        </div>
    @endif
    <div class="md:col-span-2">
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('description', $asset?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>
</div>
<div class="mt-8 flex justify-end gap-3"><a href="{{ route('fixed-assets.index') }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600">Annuler</a><x-primary-button>{{ $submitLabel }}</x-primary-button></div>
