@php
    use App\Modules\Documents\Enums\DocumentLanguage;
    use App\Modules\Parties\Enums\PartyType;
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div>
        <x-input-label for="name" value="Nom ou raison sociale *" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $party?->name)" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="type" value="Type *" />
        <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
            @foreach (PartyType::cases() as $partyType)
                <option value="{{ $partyType->value }}" @selected(old('type', $party?->type?->value) === $partyType->value)>
                    {{ $partyType->label() }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('type')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="tax_identifier" value="Identifiant fiscal" />
        <x-text-input id="tax_identifier" name="tax_identifier" class="mt-1 block w-full" :value="old('tax_identifier', $party?->tax_identifier)" />
        <x-input-error :messages="$errors->get('tax_identifier')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="E-mail" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $party?->email)" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="phone" value="Téléphone" />
        <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $party?->phone)" />
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="document_language" value="Langue des factures" />
        <select id="document_language" name="document_language" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (DocumentLanguage::cases() as $language)
                <option value="{{ $language->value }}" @selected(old('document_language', $party?->document_language?->value ?? DocumentLanguage::French->value) === $language->value)>{{ $language->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('document_language')" class="mt-2" />
    </div>

    <div class="flex items-center pt-7">
        <input type="hidden" name="is_active" value="0">
        <input id="is_active" name="is_active" type="checkbox" value="1"
               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
               @checked(old('is_active', $party?->is_active ?? true))>
        <label for="is_active" class="ms-2 text-sm text-gray-700">Tiers actif</label>
    </div>

    <div class="md:col-span-2">
        <x-input-label for="address" value="Adresse" />
        <textarea id="address" name="address" rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('address', $party?->address) }}</textarea>
        <x-input-error :messages="$errors->get('address')" class="mt-2" />
    </div>
</div>

<div class="mt-8 flex items-center justify-end gap-3">
    <a href="{{ route('parties.index') }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">
        Annuler
    </a>
    <x-primary-button>{{ $submitLabel }}</x-primary-button>
</div>
