@php
    $initialLines = old('lines', [
        ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
        ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
    ]);
@endphp

<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Comptabilité générale</p><h1 class="text-2xl font-semibold text-gray-900">Nouvelle écriture manuelle</h1><p class="mt-1 text-sm text-gray-500">Pour les opérations non couvertes par les ventes ou règlements automatiques ; le total des débits doit égaler le total des crédits.</p></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('accounting.entries.store') }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8"
            x-data='{
                lines: @json(array_values($initialLines)),
                addLine() { this.lines.push({ account_id: "", description: "", debit: "", credit: "" }) },
                amount(value) { const number = Number(value); return Number.isFinite(number) ? number : 0 },
                get debitTotal() { return this.lines.reduce((sum, line) => sum + this.amount(line.debit), 0) },
                get creditTotal() { return this.lines.reduce((sum, line) => sum + this.amount(line.credit), 0) },
                get balanced() { return this.debitTotal > 0 && Math.abs(this.debitTotal - this.creditTotal) < 0.001 },
                format(value) { return new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) }
            }'>
            @csrf
            <div class="grid gap-6 md:grid-cols-4">
                <div><x-input-label for="journal_id" value="Journal *" /><select id="journal_id" name="journal_id" class="mt-1 block w-full rounded-md border-gray-300" required><option value="">Sélectionner</option>@foreach($journals as $journal)<option value="{{ $journal->id }}" @selected((string)old('journal_id') === (string)$journal->id)>{{ $journal->code }} — {{ $journal->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('journal_id')" class="mt-2" /></div>
                <div><x-input-label for="entry_date" value="Date *" /><x-text-input id="entry_date" name="entry_date" type="date" class="mt-1 block w-full" :value="old('entry_date', today()->format('Y-m-d'))" required /><x-input-error :messages="$errors->get('entry_date')" class="mt-2" /></div>
                <div><x-input-label for="currency" value="Devise *" /><select id="currency" name="currency" class="mt-1 block w-full rounded-md border-gray-300"><option value="CDF" @selected(old('currency') === 'CDF')>CDF</option><option value="USD" @selected(old('currency', 'USD') === 'USD')>USD</option></select></div>
                <div><x-input-label for="label" value="Libellé général *" /><x-text-input id="label" name="label" class="mt-1 block w-full" :value="old('label')" required /><x-input-error :messages="$errors->get('label')" class="mt-2" /></div>
            </div>

            <div class="mt-8 overflow-x-auto">
                <table class="min-w-full">
                    <thead><tr class="border-b bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><th class="px-3 py-3">Compte</th><th class="px-3 py-3">Libellé</th><th class="px-3 py-3 text-right">Débit</th><th class="px-3 py-3 text-right">Crédit</th><th class="w-12"></th></tr></thead>
                    <tbody>
                        <template x-for="(line, index) in lines" :key="index">
                            <tr class="border-b">
                                <td class="px-3 py-3"><select x-model="line.account_id" :name="`lines[${index}][account_id]`" class="w-56 rounded-md border-gray-300 text-sm" required><option value="">Sélectionner</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></td>
                                <td class="px-3 py-3"><input x-model="line.description" :name="`lines[${index}][description]`" class="w-full min-w-52 rounded-md border-gray-300 text-sm" required></td>
                                <td class="px-3 py-3"><input x-model="line.debit" :name="`lines[${index}][debit]`" type="number" min="0" step="0.01" class="w-36 rounded-md border-gray-300 text-right text-sm" @input="if (amount(line.debit) > 0) line.credit = ''"></td>
                                <td class="px-3 py-3"><input x-model="line.credit" :name="`lines[${index}][credit]`" type="number" min="0" step="0.01" class="w-36 rounded-md border-gray-300 text-right text-sm" @input="if (amount(line.credit) > 0) line.debit = ''"></td>
                                <td class="px-3 py-3"><button type="button" @click="if(lines.length > 2) lines.splice(index, 1)" :disabled="lines.length <= 2" class="text-lg text-red-500 disabled:text-gray-300">×</button></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot><tr class="font-semibold"><td colspan="2" class="px-3 py-4 text-right">Totaux</td><td class="px-3 py-4 text-right" x-text="format(debitTotal)"></td><td class="px-3 py-4 text-right" x-text="format(creditTotal)"></td><td></td></tr></tfoot>
                </table>
            </div>
            <x-input-error :messages="$errors->get('lines')" class="mt-3" />
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" @click="addLine()" class="text-left text-sm font-semibold text-indigo-600">+ Ajouter une ligne</button>
                <p class="rounded-md px-3 py-2 text-sm font-semibold" :class="balanced ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'" x-text="balanced ? 'Écriture équilibrée' : `Écart : ${format(Math.abs(debitTotal - creditTotal))}`"></p>
            </div>
            <div class="mt-8 rounded-lg bg-gray-50 p-4 text-sm text-gray-600">L’écriture sera enregistrée en brouillon. Elle n’affectera les rapports qu’après sa comptabilisation.</div>
            <div class="mt-8 flex justify-end gap-3"><a href="{{ route('accounting.entries.index') }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600">Annuler</a><x-primary-button>Créer le brouillon</x-primary-button></div>
        </form>
    </div></div>
</x-app-layout>
