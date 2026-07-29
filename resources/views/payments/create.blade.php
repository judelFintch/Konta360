@php use App\Modules\Payments\Enums\PaymentMethod; @endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">{{ $invoice->number }}</p>
            <h1 class="text-2xl font-semibold text-gray-900">Enregistrer un règlement</h1>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 grid gap-4 rounded-xl bg-indigo-950 p-6 text-white sm:grid-cols-3">
                <div><p class="text-xs uppercase tracking-wide text-indigo-300">Client</p><p class="mt-1 font-semibold">{{ $invoice->party->name }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-indigo-300">Total facture</p><p class="mt-1 font-semibold">{{ number_format((float) $invoice->total, 2, ',', ' ') }} {{ $invoice->currency }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-indigo-300">Solde restant</p><p class="mt-1 text-xl font-semibold">{{ number_format($invoice->balanceDue(), 2, ',', ' ') }} {{ $invoice->currency }}</p></div>
            </div>

            <form method="POST" action="{{ route('payments.store', $invoice) }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                @csrf
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="payment_date" value="Date du règlement *" />
                        <x-text-input id="payment_date" name="payment_date" type="date" max="{{ today()->format('Y-m-d') }}" class="mt-1 block w-full" :value="old('payment_date', today()->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('payment_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="amount" value="Montant *" />
                        <div class="relative mt-1">
                            <x-text-input id="amount" name="amount" type="number" min="0.01" max="{{ $invoice->balanceDue() }}" step="0.01" class="block w-full pe-16" :value="old('amount', number_format($invoice->balanceDue(), 2, '.', ''))" required />
                            <span class="absolute inset-y-0 end-3 flex items-center text-sm font-medium text-gray-500">{{ $invoice->currency }}</span>
                        </div>
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="method" value="Mode de paiement *" />
                        <select id="method" name="method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            @foreach (PaymentMethod::cases() as $method)
                                <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('method')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="reference" value="Référence bancaire ou externe" />
                        <x-text-input id="reference" name="reference" class="mt-1 block w-full" :value="old('reference')" />
                        <x-input-error :messages="$errors->get('reference')" class="mt-2" />
                    </div>
                    @if ($treasuryAccounts->isNotEmpty())
                        <div class="sm:col-span-2">
                            <x-input-label for="treasury_account_id" value="Compte de trésorerie destinataire *" />
                            <select id="treasury_account_id" name="treasury_account_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                                <option value="">Sélectionner un compte</option>
                                @foreach($treasuryAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string)old('treasury_account_id') === (string)$account->id)>{{ $account->name }} — {{ number_format($account->balance(), 2, ',', ' ') }} {{ $account->currency }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('treasury_account_id')" class="mt-2" />
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3">
                    <a href="{{ route('invoices.show', $invoice) }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">Annuler</a>
                    <x-primary-button>Enregistrer le règlement</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
