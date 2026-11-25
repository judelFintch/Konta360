@php use App\Modules\Treasury\Enums\TreasuryAccountType; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600"><a href="{{ route('treasury.index') }}">Trésorerie</a></p>
                <h1 class="text-2xl font-semibold text-gray-900">Rapports imprimables</h1>
                <p class="mt-1 text-sm text-gray-500">Documents A4 conformes aux usages SYSCOHADA : identité légale de la société, période, report à nouveau, totaux de contrôle, pagination et visas.</p>
            </div>
            <a href="{{ route('treasury.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700">← Retour à la trésorerie</a>
        </div>
    </x-slot>

    @php
        $card = 'rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200';
        $input = 'mt-1 block w-full rounded-md border-gray-300 text-sm';
        $button = 'rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500';
        $cashAccounts = $accounts->where('type', TreasuryAccountType::Cash);
    @endphp

    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Registre simple des entrées et sorties</h2>
                <p class="mt-1 text-sm text-gray-500">Liste chronologique des mouvements d’un compte, avec motif, référence, montant reçu ou payé et solde après chaque opération. Les totaux permettent de vérifier le solde final.</p>
                @if ($accounts->isEmpty())
                    <p class="mt-5 rounded-lg bg-gray-50 p-4 text-sm text-gray-500">Ajoutez un compte de caisse ou de banque pour créer ce rapport.</p>
                @else
                    <form method="GET" action="{{ route('treasury.reports.entries-exits') }}" target="_blank" class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-3"><label class="text-sm font-medium text-gray-700">Compte</label>
                            <select name="account_id" class="{{ $input }}" required>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }} — {{ $account->type->label() }} ({{ $account->currency }})</option>@endforeach</select>
                        </div>
                        <div><label class="text-sm font-medium text-gray-700">Du</label><input type="date" name="date_from" value="{{ $dateFrom }}" class="{{ $input }}" required></div>
                        <div><label class="text-sm font-medium text-gray-700">Au</label><input type="date" name="date_to" value="{{ $dateTo }}" class="{{ $input }}" required></div>
                        <div class="sm:col-span-3 flex justify-end"><button class="{{ $button }}">Imprimer le registre</button></div>
                    </form>
                @endif
            </section>

            <section class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Journal de caisse / de banque</h2>
                <p class="mt-1 text-sm text-gray-500">Brouillard chronologique : solde reporté, chaque pièce avec sa nature, encaissements, décaissements, solde progressif, pointage bancaire et solde de clôture.</p>
                <form method="GET" action="{{ route('treasury.reports.journal') }}" target="_blank" class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="text-sm font-medium text-gray-700">Compte</label>
                        <select name="account_id" class="{{ $input }}"><option value="">Tous les comptes (un journal par compte)</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }} — {{ $account->type->label() }} ({{ $account->currency }})</option>@endforeach</select>
                    </div>
                    <div><label class="text-sm font-medium text-gray-700">Du</label><input type="date" name="date_from" value="{{ $dateFrom }}" class="{{ $input }}" required></div>
                    <div><label class="text-sm font-medium text-gray-700">Au</label><input type="date" name="date_to" value="{{ $dateTo }}" class="{{ $input }}" required></div>
                    <div class="sm:col-span-2 flex justify-end"><button class="{{ $button }}">Imprimer le journal</button></div>
                </form>
            </section>

            <section class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Situation de trésorerie</h2>
                <p class="mt-1 text-sm text-gray-500">Soldes d’ouverture et de clôture de chaque banque et caisse, sous-totaux par nature et total par devise, avec signalement des découverts et des caisses créditrices.</p>
                <form method="GET" action="{{ route('treasury.reports.position') }}" target="_blank" class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label class="text-sm font-medium text-gray-700">Du</label><input type="date" name="date_from" value="{{ $dateFrom }}" class="{{ $input }}" required></div>
                    <div><label class="text-sm font-medium text-gray-700">Au</label><input type="date" name="date_to" value="{{ $dateTo }}" class="{{ $input }}" required></div>
                    <div class="sm:col-span-2 flex justify-end"><button class="{{ $button }}">Imprimer la situation</button></div>
                </form>
            </section>

            <section class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Tableau des encaissements et décaissements</h2>
                <p class="mt-1 text-sm text-gray-500">Flux de trésorerie par nature (méthode directe) : trésorerie d’ouverture, encaissements, décaissements, flux net, trésorerie de clôture contrôlée, puis évolution mois par mois.</p>
                <form method="GET" action="{{ route('treasury.reports.flows') }}" target="_blank" class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div><label class="text-sm font-medium text-gray-700">Devise</label><select name="currency" class="{{ $input }}">@foreach (['CDF', 'USD'] as $currency)<option value="{{ $currency }}" @selected($defaultCurrency === $currency)>{{ $currency }}</option>@endforeach</select></div>
                    <div><label class="text-sm font-medium text-gray-700">Du</label><input type="date" name="date_from" value="{{ today()->startOfYear()->toDateString() }}" class="{{ $input }}" required></div>
                    <div><label class="text-sm font-medium text-gray-700">Au</label><input type="date" name="date_to" value="{{ $dateTo }}" class="{{ $input }}" required></div>
                    <div class="sm:col-span-3 flex justify-end"><button class="{{ $button }}">Imprimer le tableau</button></div>
                </form>
            </section>

            <section class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Procès-verbal d’arrêté de caisse</h2>
                <p class="mt-1 text-sm text-gray-500">Fiche de comptage contradictoire : solde théorique du journal, grille de billetage par coupure, calcul de l’écart (excédent ou manquant) et visas.</p>
                @if ($cashAccounts->isEmpty())
                    <p class="mt-5 rounded-lg bg-gray-50 p-4 text-sm text-gray-500">Aucune caisse n’est enregistrée.</p>
                @else
                    <form method="GET" action="{{ route('treasury.reports.cash-count') }}" target="_blank" class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div><label class="text-sm font-medium text-gray-700">Caisse</label><select name="account_id" class="{{ $input }}" required>@foreach ($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }} ({{ $account->currency }})</option>@endforeach</select></div>
                        <div><label class="text-sm font-medium text-gray-700">Date de l’arrêté</label><input type="date" name="date" value="{{ $dateTo }}" max="{{ $dateTo }}" class="{{ $input }}" required></div>
                        <div class="sm:col-span-2 flex justify-end"><button class="{{ $button }}">Imprimer le PV</button></div>
                    </form>
                @endif
            </section>
        </div>

        <p class="mt-6 text-sm text-gray-500">
            L’état de rapprochement bancaire s’imprime depuis chaque
            <a href="{{ route('treasury.reconciliations.index') }}" class="font-semibold text-indigo-600">rapprochement validé</a>.
            Les documents s’ouvrent dans un nouvel onglet : utilisez « Imprimer » ou « Télécharger » du lecteur PDF.
        </p>
    </div></div>
</x-app-layout>
