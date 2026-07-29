@php use App\Modules\Administration\Enums\Permission; use App\Modules\Expenses\Enums\ExpenseStatus; @endphp
<x-app-layout>
    <x-slot name="header"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div><p class="text-sm font-medium text-indigo-600">Dépense fournisseur</p><h1 class="text-2xl font-semibold text-gray-900">{{ $expense->number }}</h1></div>
        @if ($expense->status === ExpenseStatus::Draft)
            @can(Permission::AccountingEntriesPost->value)
                <form method="POST" action="{{ route('expenses.validate',$expense) }}">@csrf @method('PATCH')<button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Valider et comptabiliser</button></form>
            @endcan
        @endif
    </div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        @if(session('success'))<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <dl class="grid gap-5 sm:grid-cols-4"><div><dt class="text-xs uppercase text-gray-500">Fournisseur</dt><dd class="mt-1 font-semibold">{{ $expense->supplier?->name ?? '—' }}</dd></div><div><dt class="text-xs uppercase text-gray-500">Date</dt><dd class="mt-1 font-semibold">{{ $expense->expense_date->format('d/m/Y') }}</dd></div><div><dt class="text-xs uppercase text-gray-500">Échéance</dt><dd class="mt-1 font-semibold">{{ $expense->due_date->format('d/m/Y') }}</dd></div><div><dt class="text-xs uppercase text-gray-500">Statut</dt><dd class="mt-1 font-semibold">{{ $expense->status->label() }}</dd></div></dl>
            <div class="mt-6 rounded-xl bg-gray-50 p-5"><h2 class="font-semibold">{{ $expense->description }}</h2><dl class="mt-4 grid gap-4 sm:grid-cols-4"><div><dt class="text-xs text-gray-500">HT</dt><dd class="font-semibold">{{ number_format((float)$expense->subtotal,2,',',' ') }}</dd></div><div><dt class="text-xs text-gray-500">Taxe</dt><dd class="font-semibold">{{ number_format((float)$expense->tax_total,2,',',' ') }}</dd></div><div><dt class="text-xs text-gray-500">Total</dt><dd class="font-semibold">{{ number_format((float)$expense->total,2,',',' ') }} {{ $expense->currency }}</dd></div><div><dt class="text-xs text-gray-500">Reste à payer</dt><dd class="font-semibold text-indigo-700">{{ number_format($expense->balanceDue(),2,',',' ') }} {{ $expense->currency }}</dd></div></dl></div>
            @if ($expense->status === ExpenseStatus::Validated && $expense->balanceDue() > 0)
                @can(Permission::TreasuryManage->value)
                <form method="POST" action="{{ route('expenses.pay',$expense) }}" class="mt-8 grid gap-4 rounded-xl border border-emerald-200 bg-emerald-50 p-5 sm:grid-cols-2 lg:grid-cols-4">@csrf
                    <div><x-input-label for="treasury_account_id" value="Compte à débiter *" /><select id="treasury_account_id" name="treasury_account_id" class="mt-1 block w-full rounded-md border-gray-300" required><option value="">Sélectionner</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }} — {{ number_format($account->balance(),2,',',' ') }}</option>@endforeach</select></div>
                    <div><x-input-label for="payment_date" value="Date *" /><x-text-input id="payment_date" name="payment_date" type="date" max="{{ today()->format('Y-m-d') }}" class="mt-1 block w-full" :value="today()->format('Y-m-d')" required /></div>
                    <div><x-input-label for="amount" value="Montant *" /><x-text-input id="amount" name="amount" type="number" min="0.01" max="{{ $expense->balanceDue() }}" step="0.01" class="mt-1 block w-full" :value="$expense->balanceDue()" required /></div>
                    <div><x-input-label for="reference" value="Référence" /><x-text-input id="reference" name="reference" class="mt-1 block w-full" /><div class="mt-3"><x-primary-button>Payer</x-primary-button></div></div>
                    <x-input-error :messages="$errors->get('amount')" class="sm:col-span-2 lg:col-span-4" />
                </form>
                @endcan
            @endif
            <div class="mt-8 overflow-x-auto"><h2 class="mb-3 font-semibold">Paiements fournisseurs</h2><table class="min-w-full divide-y"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Numéro</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Compte</th><th class="px-4 py-3 text-right">Montant</th></tr></thead><tbody class="divide-y">@forelse($expense->payments as $payment)<tr><td class="px-4 py-3 font-semibold">{{ $payment->number }}</td><td class="px-4 py-3">{{ $payment->payment_date->format('d/m/Y') }}</td><td class="px-4 py-3">{{ $payment->treasuryAccount->name }}</td><td class="px-4 py-3 text-right font-semibold">{{ number_format((float)$payment->amount,2,',',' ') }} {{ $payment->currency }}</td></tr>@empty<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Aucun paiement.</td></tr>@endforelse</tbody></table></div>
        </article>
    </div></div>
</x-app-layout>
