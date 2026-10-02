<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Administration</p><h1 class="text-2xl font-semibold text-gray-900">Paramètres de l’entreprise</h1><p class="mt-1 text-sm text-gray-500">Ces informations apparaissent sur les documents commerciaux imprimés et PDF.</p></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <form method="POST" action="{{ route('administration.company.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf @method('PUT')
            <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <h2 class="mb-6 text-lg font-semibold">Identité légale</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                <div><x-input-label for="name" value="Raison sociale *" /><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name',$company->name)" required /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                <div><x-input-label for="legal_form" value="Forme juridique" /><x-text-input id="legal_form" name="legal_form" class="mt-1 block w-full" :value="old('legal_form',$company->legal_form)" placeholder="SARL, SA…" /></div>
                <div><x-input-label for="tax_identifier" value="Numéro fiscal" /><x-text-input id="tax_identifier" name="tax_identifier" class="mt-1 block w-full" :value="old('tax_identifier',$company->tax_identifier)" /></div>
                <div><x-input-label for="national_identifier" value="Identification nationale (ID Nat)" /><x-text-input id="national_identifier" name="national_identifier" class="mt-1 block w-full" :value="old('national_identifier',$company->national_identifier)" /></div>
                <div><x-input-label for="cnss_number" value="Numéro CNSS" /><x-text-input id="cnss_number" name="cnss_number" class="mt-1 block w-full" :value="old('cnss_number',$company->cnss_number)" /></div>
                <div><x-input-label for="trade_register" value="RCCM / Registre de commerce" /><x-text-input id="trade_register" name="trade_register" class="mt-1 block w-full" :value="old('trade_register',$company->trade_register)" /></div>
                <div><x-input-label for="representative_name" value="Responsable légal" /><x-text-input id="representative_name" name="representative_name" class="mt-1 block w-full" :value="old('representative_name',$company->representative_name)" /></div>
                <div><x-input-label for="representative_title" value="Fonction du responsable" /><x-text-input id="representative_title" name="representative_title" class="mt-1 block w-full" :value="old('representative_title',$company->representative_title)" placeholder="Gérant, Directeur général…" /></div>
                <div><x-input-label for="email" value="Adresse e-mail" /><x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email',$company->email)" /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
                <div><x-input-label for="phone" value="Téléphone" /><x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone',$company->phone)" /></div>
                <div><x-input-label for="website" value="Site internet" /><x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website',$company->website)" placeholder="https://…" /><x-input-error :messages="$errors->get('website')" class="mt-2" /></div>
                <div class="sm:col-span-2"><x-input-label for="address" value="Adresse complète" /><textarea id="address" name="address" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('address',$company->address) }}</textarea></div>
            </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <h2 class="mb-6 text-lg font-semibold">Paiements et règles commerciales</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                <div><x-input-label for="bank_name" value="Banque" /><x-text-input id="bank_name" name="bank_name" class="mt-1 block w-full" :value="old('bank_name',$company->bank_name)" /></div>
                <div><x-input-label for="bank_account_name" value="Titulaire du compte" /><x-text-input id="bank_account_name" name="bank_account_name" class="mt-1 block w-full" :value="old('bank_account_name',$company->bank_account_name)" /></div>
                <div><x-input-label for="bank_account_number" value="Numéro de compte / IBAN" /><x-text-input id="bank_account_number" name="bank_account_number" class="mt-1 block w-full" :value="old('bank_account_number',$company->bank_account_number)" /></div>
                <div><x-input-label for="bank_swift" value="Code SWIFT" /><x-text-input id="bank_swift" name="bank_swift" class="mt-1 block w-full" :value="old('bank_swift',$company->bank_swift)" /></div>
                <div class="sm:col-span-2"><x-input-label for="mobile_money" value="Mobile Money" /><x-text-input id="mobile_money" name="mobile_money" class="mt-1 block w-full" :value="old('mobile_money',$company->mobile_money)" placeholder="Opérateur et numéro" /></div>
                <div><x-input-label for="default_currency" value="Devise principale *" /><select id="default_currency" name="default_currency" class="mt-1 block w-full rounded-md border-gray-300"><option value="CDF" @selected(old('default_currency',$company->default_currency)==='CDF')>CDF</option><option value="USD" @selected(old('default_currency',$company->default_currency)==='USD')>USD</option></select></div>
                <div><x-input-label for="default_tax_rate" value="Taux de taxe par défaut (%) *" /><x-text-input id="default_tax_rate" name="default_tax_rate" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full" :value="old('default_tax_rate',$company->default_tax_rate)" required /></div>
                <div><x-input-label for="default_payment_days" value="Délai de paiement (jours) *" /><x-text-input id="default_payment_days" name="default_payment_days" type="number" min="0" class="mt-1 block w-full" :value="old('default_payment_days',$company->default_payment_days)" required /></div>
                <div><x-input-label for="default_quote_validity_days" value="Validité des devis (jours) *" /><x-text-input id="default_quote_validity_days" name="default_quote_validity_days" type="number" min="1" class="mt-1 block w-full" :value="old('default_quote_validity_days',$company->default_quote_validity_days)" required /></div>
            </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <h2 class="mb-2 text-lg font-semibold">Numérotation automatique</h2>
            <p class="mb-6 text-sm text-gray-500">Les nouveaux numéros garderont le format PRÉFIXE-ANNÉE-NUMÉRO. Les documents déjà créés ne changent pas.</p>
            <div class="grid gap-6 sm:grid-cols-4">
                <div><x-input-label for="quote_prefix" value="Préfixe devis *" /><x-text-input id="quote_prefix" name="quote_prefix" class="mt-1 block w-full uppercase" :value="old('quote_prefix',$company->quote_prefix)" required /></div>
                <div><x-input-label for="invoice_prefix" value="Préfixe factures *" /><x-text-input id="invoice_prefix" name="invoice_prefix" class="mt-1 block w-full uppercase" :value="old('invoice_prefix',$company->invoice_prefix)" required /></div>
                <div><x-input-label for="credit_note_prefix" value="Préfixe avoirs *" /><x-text-input id="credit_note_prefix" name="credit_note_prefix" class="mt-1 block w-full uppercase" :value="old('credit_note_prefix',$company->credit_note_prefix)" required /></div>
                <div><x-input-label for="number_padding" value="Nombre de chiffres *" /><x-text-input id="number_padding" name="number_padding" type="number" min="3" max="10" class="mt-1 block w-full" :value="old('number_padding',$company->number_padding)" required /></div>
            </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <h2 class="mb-6 text-lg font-semibold">Documents et visuels</h2>
            <div class="grid gap-6 sm:grid-cols-3">
                @foreach(['logo' => 'Logo', 'signature' => 'Signature', 'stamp' => 'Cachet'] as $field => $label)
                    <div><x-input-label :for="$field" :value="$label.' (PNG/JPG/WEBP, 2 Mo max)'" />@if($company->{$field.'_path'})<img src="{{ route('administration.company.asset', $field) }}" alt="{{ $label }}" class="my-3 h-20 max-w-full object-contain">@endif<input id="{{ $field }}" name="{{ $field }}" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm"><x-input-error :messages="$errors->get($field)" class="mt-2" /></div>
                @endforeach
                <div class="sm:col-span-3"><x-input-label for="invoice_footer" value="Mentions et conditions affichées sur les documents" /><textarea id="invoice_footer" name="invoice_footer" rows="4" class="mt-1 block w-full rounded-md border-gray-300" placeholder="Conditions, coordonnées de paiement ou mentions légales">{{ old('invoice_footer',$company->invoice_footer) }}</textarea></div>
            </div>
            </section>

            <div class="flex justify-end"><x-primary-button>Enregistrer tous les paramètres</x-primary-button></div>
        </form>
    </div></div>
</x-app-layout>
