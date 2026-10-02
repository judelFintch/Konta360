@php use App\Modules\Treasury\Enums\TreasuryAccountType; use App\Modules\Treasury\Enums\TreasuryTransactionType; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm font-medium text-indigo-600">Finance</p><h1 class="text-2xl font-semibold text-gray-900">Trésorerie</h1><p class="mt-1 text-sm text-gray-500">Suivez les soldes de vos banques et caisses, ainsi que le journal des entrées, sorties et virements.</p></div>
            <div class="flex flex-wrap gap-3">
                @can(\App\Modules\Administration\Enums\Permission::ReportsExport->value)<a href="{{ route('exports.treasury') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700">Exporter CSV</a>@endcan
                <a href="{{ route('treasury.reconciliations.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700">Rapprochements bancaires</a>
                @if ($accounts->isNotEmpty())<a href="{{ route('treasury.transactions.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Nouveau mouvement</a>@endif
            </div>
        </div>
    </x-slot>

    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if (session('success'))<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($accounts as $account)
                <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase text-gray-500">{{ $account->type->label() }}</p><h2 class="mt-1 font-semibold text-gray-900">{{ $account->name }}</h2></div><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">{{ $account->currency }}</span></div>
                    <p class="mt-5 text-2xl font-semibold {{ $account->balance() < 0 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format($account->balance(), 2, ',', ' ') }} {{ $account->currency }}</p>
                    <p class="mt-1 text-xs text-gray-500">Solde initial : {{ number_format((float) $account->opening_balance, 2, ',', ' ') }}</p>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500 md:col-span-2">Créez votre premier compte bancaire ou votre première caisse.</div>
            @endforelse
        </div>

        <details class="mt-6 rounded-xl bg-white shadow-sm ring-1 ring-gray-200" @if($errors->hasAny(['name', 'currency', 'opening_balance'])) open @endif>
            <summary class="cursor-pointer px-6 py-4 font-semibold text-indigo-600">Ajouter un compte bancaire ou une caisse</summary>
            <form method="POST" action="{{ route('treasury.accounts.store') }}" class="grid gap-5 border-t p-6 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <div><x-input-label for="name" value="Nom *" /><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" placeholder="Banque principale" required /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                <div><x-input-label for="type" value="Type *" /><select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300">@foreach(TreasuryAccountType::cases() as $item)<option value="{{ $item->value }}" @selected(old('type') === $item->value)>{{ $item->label() }}</option>@endforeach</select></div>
                <div><x-input-label for="currency" value="Devise *" /><select id="currency" name="currency" class="mt-1 block w-full rounded-md border-gray-300"><option value="CDF" @selected(old('currency', \App\Models\CompanySetting::current()->default_currency) === 'CDF')>CDF</option><option value="USD" @selected(old('currency', \App\Models\CompanySetting::current()->default_currency) === 'USD')>USD</option></select></div>
                <div><x-input-label for="opening_balance" value="Solde initial *" /><x-text-input id="opening_balance" name="opening_balance" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('opening_balance', 0)" required /><x-input-error :messages="$errors->get('opening_balance')" class="mt-2" /></div>
                <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><x-primary-button>Créer le compte</x-primary-button></div>
            </form>
        </details>

        @if ($pendingPayments->isNotEmpty())
            <section class="mt-8 overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
                <div class="border-b border-amber-200 bg-amber-50 px-6 py-5">
                    <h2 class="font-semibold text-amber-950">Règlements historiques à affecter</h2>
                    <p class="mt-1 text-sm text-amber-800">Ces règlements ont été enregistrés avant la création de la trésorerie. Choisissez le compte qui avait réellement reçu chaque montant.</p>
                </div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="px-5 py-3">Règlement</th><th class="px-5 py-3">Client / facture</th><th class="px-5 py-3">Date</th><th class="px-5 py-3 text-right">Montant</th><th class="px-5 py-3">Compte destinataire</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($pendingPayments as $payment)
                            @php $compatibleAccounts = $accounts->where('is_active', true)->where('currency', $payment->currency); @endphp
                            <tr>
                                <td class="px-5 py-4 font-semibold">{{ $payment->number }}</td>
                                <td class="px-5 py-4 text-sm"><p>{{ $payment->invoice->party->name }}</p><a href="{{ route('invoices.show', $payment->invoice) }}" class="text-xs font-semibold text-indigo-600">{{ $payment->invoice->number }}</a></td>
                                <td class="px-5 py-4 text-sm">{{ $payment->payment_date->format('d/m/Y') }}</td>
                                <td class="px-5 py-4 text-right font-semibold">{{ number_format((float)$payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</td>
                                <td class="px-5 py-4">
                                    @if($compatibleAccounts->isNotEmpty())
                                        <form method="POST" action="{{ route('treasury.payments.assign', $payment) }}" class="flex min-w-72 gap-2">
                                            @csrf
                                            <select name="treasury_account_id" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm" required><option value="">Choisir</option>@foreach($compatibleAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select>
                                            <button class="rounded-md bg-amber-500 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-400">Affecter</button>
                                        </form>
                                    @else
                                        <span class="text-sm text-red-600">Créez d’abord un compte en {{ $payment->currency }}.</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            </section>
        @endif

        <section class="mt-8">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-semibold text-gray-900">Journal de trésorerie</h2><p class="text-sm text-gray-500">Toutes les entrées, sorties et virements comptabilisés.</p></div></div>
            <form method="GET" class="mb-4 grid gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 sm:grid-cols-[1fr_220px_auto]">
                <select name="account_id" class="rounded-md border-gray-300"><option value="">Tous les comptes</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($accountId === $account->id)>{{ $account->name }} ({{ $account->currency }})</option>@endforeach</select>
                <select name="type" class="rounded-md border-gray-300"><option value="">Tous les mouvements</option>@foreach(TreasuryTransactionType::cases() as $item)<option value="{{ $item->value }}" @selected($type === $item->value)>{{ $item->label() }}</option>@endforeach</select>
                <x-secondary-button type="submit">Filtrer</x-secondary-button>
            </form>
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="px-5 py-3">Numéro</th><th class="px-5 py-3">Date</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Compte</th><th class="px-5 py-3">Libellé</th><th class="px-5 py-3 text-right">Montant</th></tr></thead>
                <tbody class="divide-y divide-gray-100">@forelse($transactions as $transaction)
                    <tr><td class="px-5 py-4 font-semibold text-indigo-600">{{ $transaction->number }}</td><td class="px-5 py-4 text-sm">{{ $transaction->transaction_date->format('d/m/Y') }}</td><td class="px-5 py-4 text-sm">{{ $transaction->type->label() }}</td><td class="px-5 py-4 text-sm">{{ $transaction->account->name }}@if($transaction->destinationAccount) → {{ $transaction->destinationAccount->name }}@endif</td><td class="px-5 py-4 text-sm"><p>{{ $transaction->description }}</p>@if($transaction->reference)<p class="text-xs text-gray-500">{{ $transaction->reference }}</p>@endif</td><td class="px-5 py-4 text-right font-semibold {{ $transaction->type === TreasuryTransactionType::Outflow ? 'text-red-600' : ($transaction->type === TreasuryTransactionType::Inflow ? 'text-emerald-600' : 'text-gray-900') }}">{{ $transaction->type === TreasuryTransactionType::Outflow ? '−' : ($transaction->type === TreasuryTransactionType::Inflow ? '+' : '') }} {{ number_format((float)$transaction->amount,2,',',' ') }} {{ $transaction->currency }}</td></tr>
                @empty<tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Aucun mouvement de trésorerie.</td></tr>@endforelse</tbody>
            </table></div>@if($transactions->hasPages())<div class="border-t px-6 py-4">{{ $transactions->links() }}</div>@endif</div>
        </section>
    </div></div>
</x-app-layout>
