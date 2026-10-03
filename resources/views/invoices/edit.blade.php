@php
    use App\Modules\Documents\Enums\DocumentLanguage;
    use App\Modules\Invoices\Enums\DeductionType;
    use App\Modules\Payments\Enums\PaymentMethod;

    $initialDeductions = old('deductions') ?? $invoice->deductions->map(fn ($deduction) => [
        'type' => $deduction->type->value,
        'description' => $deduction->description,
        'quantity' => (float) $deduction->quantity,
        'unit_price' => (float) $deduction->unit_price,
        'received_on' => $deduction->received_on?->format('Y-m-d'),
        'payment_method' => $deduction->payment_method?->value,
        'treasury_account_id' => (string) $deduction->treasury_account_id,
        'reference' => $deduction->reference,
    ])->values()->all();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div><p class="text-sm font-medium text-indigo-600">Facture brouillon #{{ $invoice->id }}</p><h1 class="text-2xl font-semibold text-gray-900">Informations de facturation</h1><p class="mt-1 text-sm text-gray-500">Ajustez les dates, notes et déductions avant de valider définitivement la facture.</p></div>
    </x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8"
                  x-data='{
                      deductions: @json($initialDeductions),
                      total: {{ (float) $invoice->total }},
                      addDeduction(type) {
                          this.deductions.push({ type, description: type === "advance" ? "Avance reçue" : "", quantity: 1, unit_price: "", received_on: "", payment_method: "", treasury_account_id: "", reference: "" })
                      },
                      amount(deduction) { return Math.round(Number(deduction.quantity || 0) * Number(deduction.unit_price || 0) * 100) / 100 },
                      deductionsTotal() { return this.deductions.reduce((sum, deduction) => sum + this.amount(deduction), 0) },
                      money(value) { return new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) }
                  }'>
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
                    <div>
                        <x-input-label for="language" value="Langue de la facture *" />
                        <select id="language" name="language" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (DocumentLanguage::cases() as $language)
                                <option value="{{ $language->value }}" @selected(old('language', $invoice->language?->value ?? DocumentLanguage::French->value) === $language->value)>{{ $language->label() }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Reprise de la fiche client ; les notes saisies ne sont pas traduites.</p>
                        <x-input-error :messages="$errors->get('language')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes et conditions" />
                        <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $invoice->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                </div>

                {{-- Déductions --}}
                <div class="mt-8 border-t border-gray-100 pt-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="font-semibold text-gray-900">Déductions</h2>
                            <p class="text-sm text-gray-500">Facultatif. Retranchées du total TTC pour obtenir le net à payer ; elles ne modifient pas la base de TVA.</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" @click="addDeduction('{{ DeductionType::Advance->value }}')" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">+ Avance reçue</button>
                            <button type="button" @click="addDeduction('{{ DeductionType::ClientExpense->value }}')" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">+ Frais client</button>
                        </div>
                    </div>

                    <x-input-error :messages="$errors->get('deductions')" class="mt-3" />

                    <div class="mt-4 space-y-3">
                        <template x-for="(deduction, index) in deductions" :key="index">
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <input type="hidden" :name="`deductions[${index}][type]`" :value="deduction.type">
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                          :class="deduction.type === 'advance' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                          x-text="deduction.type === 'advance' ? '{{ DeductionType::Advance->label() }}' : '{{ DeductionType::ClientExpense->label() }}'"></span>
                                    <button type="button" @click="deductions.splice(index, 1)" class="rounded-md px-2 text-xl text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="Supprimer la déduction">×</button>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-[minmax(200px,1fr)_100px_140px_130px]">
                                    <div>
                                        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Libellé</label>
                                        <input type="text" maxlength="255" x-model="deduction.description" :name="`deductions[${index}][description]`" required
                                               placeholder="Ex. Cost Operator, Cost Oil…"
                                               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Quantité</label>
                                        <input type="number" min="0.001" step="0.001" x-model.number="deduction.quantity" :name="`deductions[${index}][quantity]`" required
                                               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Prix unitaire</label>
                                        <input type="number" min="0.0001" step="0.0001" x-model.number="deduction.unit_price" :name="`deductions[${index}][unit_price]`" required
                                               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Montant</label>
                                        <p class="mt-3 text-right text-sm font-semibold text-gray-900" x-text="`${money(amount(deduction))} {{ $invoice->currency }}`"></p>
                                    </div>
                                </div>
                                <template x-if="deduction.type === 'advance'">
                                    <div class="mt-3 grid gap-3 sm:grid-cols-4">
                                        <div>
                                            <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Reçue le</label>
                                            <input type="date" x-model="deduction.received_on" :name="`deductions[${index}][received_on]`" required
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        <div>
                                            <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Mode</label>
                                            <select x-model="deduction.payment_method" :name="`deductions[${index}][payment_method]`" required
                                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">Sélectionner</option>
                                                @foreach (PaymentMethod::cases() as $method)
                                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @if ($treasuryAccounts->isNotEmpty())
                                            <div>
                                                <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Compte</label>
                                                <select x-model="deduction.treasury_account_id" :name="`deductions[${index}][treasury_account_id]`" required
                                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">Sélectionner</option>
                                                    @foreach ($treasuryAccounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                        <div>
                                            <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Référence</label>
                                            <input type="text" maxlength="255" x-model="deduction.reference" :name="`deductions[${index}][reference]`"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <p x-show="deductions.length === 0" class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">Aucune déduction : le net à payer est égal au total TTC.</p>
                    </div>

                    @foreach ($errors->getMessages() as $key => $messages)
                        @if (str_starts_with($key, 'deductions.'))
                            <p class="mt-2 text-sm text-red-600">Déduction n° {{ (int) explode('.', $key)[1] + 1 }} : {{ $messages[0] }}</p>
                        @endif
                    @endforeach

                    <div class="mt-4 ml-auto max-w-sm rounded-xl bg-gray-900 p-5 text-white">
                        <div class="flex justify-between text-sm text-gray-300"><span>Total TTC</span><span x-text="`${money(total)} {{ $invoice->currency }}`"></span></div>
                        <div class="mt-3 flex justify-between text-sm text-gray-300"><span>Total déductions</span><span x-text="`− ${money(deductionsTotal())}`"></span></div>
                        <div class="mt-4 flex justify-between border-t border-gray-700 pt-4 text-lg font-semibold"><span>Net à payer</span><span x-text="`${money(total - deductionsTotal())} {{ $invoice->currency }}`"></span></div>
                        <p x-show="deductionsTotal() > total" class="mt-2 text-xs text-red-300">Les déductions dépassent le total TTC.</p>
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
