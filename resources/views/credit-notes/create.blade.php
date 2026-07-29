<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Nouvel avoir</p><h1 class="text-2xl font-semibold text-gray-900">{{ $invoice->number }} — {{ $invoice->party->name }}</h1><p class="mt-1 text-sm text-gray-500">Choisissez les quantités à créditer ; l'écriture comptable inverse de la vente sera générée automatiquement.</p></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('credit-notes.store', $invoice) }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            @csrf
            <div class="grid gap-6 sm:grid-cols-2">
                <div><x-input-label for="issue_date" value="Date de l’avoir *" /><x-text-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full" :value="old('issue_date', today()->format('Y-m-d'))" required /><x-input-error :messages="$errors->get('issue_date')" class="mt-2" /></div>
                <div><x-input-label for="reason" value="Motif *" /><x-text-input id="reason" name="reason" class="mt-1 block w-full" :value="old('reason')" placeholder="Retour, erreur de facturation…" required /><x-input-error :messages="$errors->get('reason')" class="mt-2" /></div>
            </div>
            <div class="mt-8 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-3">Article</th><th class="px-4 py-3 text-right">Facturé</th><th class="px-4 py-3 text-right">Déjà crédité</th><th class="px-4 py-3 text-right">Quantité à créditer</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($invoice->lines as $line)
                            @php $available = $line->creditableQuantity(); @endphp
                            <tr><td class="px-4 py-4"><p class="font-medium">{{ $line->description }}</p><p class="text-xs text-gray-500">{{ $line->sku }} · {{ number_format((float) $line->unit_price, 2, ',', ' ') }} {{ $invoice->currency }}</p></td><td class="px-4 py-4 text-right">{{ number_format((float) $line->quantity, 3, ',', ' ') }}</td><td class="px-4 py-4 text-right">{{ number_format($line->creditedQuantity(), 3, ',', ' ') }}</td><td class="px-4 py-4"><input name="lines[{{ $line->id }}]" type="number" min="0" max="{{ $available }}" step="0.001" value="{{ old('lines.'.$line->id, 0) }}" @disabled($available <= 0) class="ms-auto block w-36 rounded-md border-gray-300 text-right"><x-input-error :messages="$errors->get('lines.'.$line->id)" class="mt-2 text-right" /></td></tr>
                        @endforeach
                    </tbody>
                </table>
                <x-input-error :messages="$errors->get('lines')" class="mt-3" />
            </div>
            <div class="mt-8 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">L’émission est définitive : elle génère immédiatement l’écriture comptable inverse de la vente.</div>
            <div class="mt-8 flex justify-end gap-3"><a href="{{ route('invoices.show', $invoice) }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600">Annuler</a><x-primary-button>Émettre l’avoir</x-primary-button></div>
        </form>
    </div></div>
</x-app-layout>
