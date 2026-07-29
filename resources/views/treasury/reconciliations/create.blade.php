<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Trésorerie</p><h1 class="text-2xl font-semibold text-gray-900">Nouveau rapprochement bancaire</h1></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <form method="GET" class="grid gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[1fr_180px_180px_auto]">
            <select name="account_id" class="rounded-md border-gray-300" required><option value="">Compte bancaire</option>@foreach($accounts as $item)<option value="{{ $item->id }}" @selected($account?->id === $item->id)>{{ $item->name }} ({{ $item->currency }})</option>@endforeach</select>
            <input name="starts_on" type="date" value="{{ $startsOn }}" class="rounded-md border-gray-300" required>
            <input name="ends_on" type="date" value="{{ $endsOn }}" class="rounded-md border-gray-300" required>
            <x-secondary-button type="submit">Afficher</x-secondary-button>
        </form>

        @if($account)
            @php
                $transactionData = $transactions->map(fn($transaction) => ['id' => $transaction->id, 'signed' => $transaction->signedAmountFor($account)])->values();
            @endphp
            <form method="POST" action="{{ route('treasury.reconciliations.store') }}" class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
                x-data='{
                    movements: @json($transactionData),
                    selected: @json(array_map("strval", old("transactions", []))),
                    opening: Number(@json(old("statement_opening_balance", 0))),
                    closing: Number(@json(old("statement_closing_balance", 0))),
                    get calculated() { return Number(this.opening || 0) + this.movements.filter(item => this.selected.includes(String(item.id))).reduce((sum, item) => sum + Number(item.signed), 0) },
                    get difference() { return Number(this.closing || 0) - this.calculated },
                    format(value) { return new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) }
                }'>
                @csrf
                <input type="hidden" name="treasury_account_id" value="{{ $account->id }}">
                <input type="hidden" name="starts_on" value="{{ $startsOn }}">
                <input type="hidden" name="ends_on" value="{{ $endsOn }}">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><x-input-label for="statement_opening_balance" value="Solde initial du relevé *" /><x-text-input id="statement_opening_balance" name="statement_opening_balance" type="number" step="0.01" class="mt-1 block w-full" x-model.number="opening" required /></div>
                    <div><x-input-label for="statement_closing_balance" value="Solde final du relevé *" /><x-text-input id="statement_closing_balance" name="statement_closing_balance" type="number" step="0.01" class="mt-1 block w-full" x-model.number="closing" required /></div>
                </div>
                <div class="mt-7 overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="w-12 px-4 py-3"></th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Mouvement</th><th class="px-4 py-3">Libellé</th><th class="px-4 py-3 text-right">Montant</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">@forelse($transactions as $transaction)<tr><td class="px-4 py-3"><input type="checkbox" name="transactions[]" value="{{ $transaction->id }}" x-model="selected" class="rounded border-gray-300 text-indigo-600"></td><td class="px-4 py-3 text-sm">{{ $transaction->transaction_date->format('d/m/Y') }}</td><td class="px-4 py-3 text-sm font-semibold">{{ $transaction->number }}</td><td class="px-4 py-3 text-sm">{{ $transaction->description }}</td><td class="px-4 py-3 text-right font-semibold {{ $transaction->signedAmountFor($account) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $transaction->signedAmountFor($account) >= 0 ? '+' : '−' }} {{ number_format(abs($transaction->signedAmountFor($account)),2,',',' ') }} {{ $account->currency }}</td></tr>@empty<tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">Aucun mouvement disponible pour cette période.</td></tr>@endforelse</tbody>
                </table></div>
                <x-input-error :messages="$errors->get('transactions')" class="mt-3" />
                <div class="mt-6 grid gap-4 rounded-xl bg-gray-50 p-5 sm:grid-cols-3"><div><p class="text-xs uppercase text-gray-500">Solde calculé</p><p class="mt-1 text-lg font-semibold"><span x-text="format(calculated)"></span> {{ $account->currency }}</p></div><div><p class="text-xs uppercase text-gray-500">Solde du relevé</p><p class="mt-1 text-lg font-semibold"><span x-text="format(closing)"></span> {{ $account->currency }}</p></div><div><p class="text-xs uppercase text-gray-500">Écart</p><p class="mt-1 text-lg font-semibold" :class="Math.abs(difference) < 0.001 ? 'text-emerald-600' : 'text-red-600'"><span x-text="format(difference)"></span> {{ $account->currency }}</p></div></div>
                <div class="mt-5"><x-input-label for="notes" value="Notes" /><textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('notes') }}</textarea></div>
                <div class="mt-8 flex justify-end gap-3"><a href="{{ route('treasury.reconciliations.index') }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600">Annuler</a><x-primary-button>Valider le rapprochement</x-primary-button></div>
            </form>
        @endif
    </div></div>
</x-app-layout>
