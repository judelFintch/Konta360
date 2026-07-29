@php use App\Modules\Treasury\Enums\TreasuryTransactionType; @endphp

<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Trésorerie</p><h1 class="text-2xl font-semibold text-gray-900">Nouveau mouvement</h1><p class="mt-1 text-sm text-gray-500">Enregistrez une entrée, une sortie ou un virement interne entre comptes de trésorerie.</p></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('treasury.transactions.store') }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8" x-data="{ type: @js(old('type', TreasuryTransactionType::Inflow->value)) }">
            @csrf
            <div class="grid gap-6 sm:grid-cols-2">
                <div><x-input-label for="type" value="Type de mouvement *" /><select id="type" name="type" x-model="type" class="mt-1 block w-full rounded-md border-gray-300">@foreach(TreasuryTransactionType::cases() as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</select><x-input-error :messages="$errors->get('type')" class="mt-2" /></div>
                <div><x-input-label for="transaction_date" value="Date *" /><x-text-input id="transaction_date" name="transaction_date" type="date" max="{{ today()->format('Y-m-d') }}" class="mt-1 block w-full" :value="old('transaction_date', today()->format('Y-m-d'))" required /><x-input-error :messages="$errors->get('transaction_date')" class="mt-2" /></div>
                <div><x-input-label for="treasury_account_id" value="Compte source *" /><select id="treasury_account_id" name="treasury_account_id" class="mt-1 block w-full rounded-md border-gray-300" required>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string)old('treasury_account_id') === (string)$account->id)>{{ $account->name }} — {{ number_format($account->balance(),2,',',' ') }} {{ $account->currency }}</option>@endforeach</select><x-input-error :messages="$errors->get('treasury_account_id')" class="mt-2" /></div>
                <div x-show="type === 'transfer'" x-cloak><x-input-label for="destination_account_id" value="Compte destinataire *" /><select id="destination_account_id" name="destination_account_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Sélectionner</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string)old('destination_account_id') === (string)$account->id)>{{ $account->name }} ({{ $account->currency }})</option>@endforeach</select><x-input-error :messages="$errors->get('destination_account_id')" class="mt-2" /></div>
                <div><x-input-label for="amount" value="Montant *" /><x-text-input id="amount" name="amount" type="number" min="0.01" step="0.01" class="mt-1 block w-full" :value="old('amount')" required /><x-input-error :messages="$errors->get('amount')" class="mt-2" /></div>
                <div><x-input-label for="reference" value="Référence" /><x-text-input id="reference" name="reference" class="mt-1 block w-full" :value="old('reference')" /></div>
                <div class="sm:col-span-2"><x-input-label for="description" value="Libellé *" /><x-text-input id="description" name="description" class="mt-1 block w-full" :value="old('description')" placeholder="Motif du mouvement" required /><x-input-error :messages="$errors->get('description')" class="mt-2" /></div>
            </div>
            <div class="mt-8 rounded-lg bg-indigo-50 p-4 text-sm text-indigo-900">Le mouvement sera comptabilisé immédiatement et ne pourra pas rendre le compte source négatif.</div>
            <div class="mt-8 flex justify-end gap-3"><a href="{{ route('treasury.index') }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600">Annuler</a><x-primary-button>Comptabiliser</x-primary-button></div>
        </form>
    </div></div>
</x-app-layout>
