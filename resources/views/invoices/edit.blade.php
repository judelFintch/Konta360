<x-app-layout>
    <x-slot name="header">
        <div><p class="text-sm font-medium text-indigo-600">Facture brouillon #{{ $invoice->id }}</p><h1 class="text-2xl font-semibold text-gray-900">Informations de facturation</h1></div>
    </x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                @csrf @method('PUT')
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="issue_date" value="Date de facture *" />
                        <x-text-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full" :value="old('issue_date', $invoice->issue_date->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('issue_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="due_date" value="Date d’échéance *" />
                        <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date', $invoice->due_date->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes et conditions" />
                        <textarea id="notes" name="notes" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $invoice->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3">
                    <a href="{{ route('invoices.show', $invoice) }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">Annuler</a>
                    <x-primary-button>Enregistrer</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
