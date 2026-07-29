<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Achats</p><h1 class="text-2xl font-semibold text-gray-900">Nouvelle dépense</h1></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('expenses.store') }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8" x-data="{ subtotal: Number(@js(old('subtotal', 0))), tax: Number(@js(old('tax_total', 0))) }">
            @csrf
            <div class="grid gap-6 sm:grid-cols-2">
                <div><x-input-label for="supplier_id" value="Fournisseur" /><select id="supplier_id" name="supplier_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Sans fournisseur</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string)old('supplier_id') === (string)$supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
                <div><x-input-label for="supplier_reference" value="Référence fournisseur" /><x-text-input id="supplier_reference" name="supplier_reference" class="mt-1 block w-full" :value="old('supplier_reference')" /></div>
                <div><x-input-label for="expense_date" value="Date *" /><x-text-input id="expense_date" name="expense_date" type="date" class="mt-1 block w-full" :value="old('expense_date',today()->format('Y-m-d'))" required /></div>
                <div><x-input-label for="due_date" value="Échéance *" /><x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date',today()->addDays(30)->format('Y-m-d'))" required /><x-input-error :messages="$errors->get('due_date')" class="mt-2" /></div>
                <div class="sm:col-span-2"><x-input-label for="description" value="Description *" /><x-text-input id="description" name="description" class="mt-1 block w-full" :value="old('description')" required /><x-input-error :messages="$errors->get('description')" class="mt-2" /></div>
                <div><x-input-label for="subtotal" value="Montant hors taxe *" /><x-text-input id="subtotal" name="subtotal" type="number" min="0.01" step="0.01" class="mt-1 block w-full" x-model.number="subtotal" required /></div>
                <div><x-input-label for="tax_total" value="Taxe déductible *" /><x-text-input id="tax_total" name="tax_total" type="number" min="0" step="0.01" class="mt-1 block w-full" x-model.number="tax" required /></div>
                <div><x-input-label for="currency" value="Devise *" /><select id="currency" name="currency" class="mt-1 block w-full rounded-md border-gray-300"><option value="CDF">CDF</option><option value="USD" @selected(old('currency','USD')==='USD')>USD</option></select></div>
                <div class="rounded-lg bg-indigo-50 p-4"><p class="text-xs uppercase text-indigo-600">Total TTC</p><p class="mt-1 text-xl font-semibold text-indigo-950" x-text="new Intl.NumberFormat('fr-FR',{minimumFractionDigits:2}).format((subtotal||0)+(tax||0))"></p></div>
            </div>
            <div class="mt-8 flex justify-end gap-3"><a href="{{ route('expenses.index') }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600">Annuler</a><x-primary-button>Créer le brouillon</x-primary-button></div>
        </form>
    </div></div>
</x-app-layout>
