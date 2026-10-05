<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Administration</p><h1 class="text-2xl font-semibold text-gray-900">Données et confidentialité</h1><p class="mt-1 text-sm text-gray-500">Export de vos données, conditions d’utilisation et clôture du compte.</p></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if (session('success'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <h2 class="text-lg font-semibold">Exporter toutes vos données</h2>
            <p class="mt-2 text-sm text-gray-600">Une archive ZIP avec une feuille CSV par table (tiers, factures, écritures, trésorerie…), vos utilisateurs et vos fichiers (logo, signature, cachet). Vous pouvez l’ouvrir avec un tableur et la conserver indépendamment de Konta360.</p>
            <a href="{{ route('administration.data.export') }}" class="mt-4 inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Télécharger l’export</a>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <h2 class="text-lg font-semibold">Conditions générales et confidentialité</h2>
            <p class="mt-2 text-sm text-gray-600">
                <a href="{{ route('legal.terms') }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">Conditions générales d’utilisation</a>
                · <a href="{{ route('legal.privacy') }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">Politique de confidentialité</a>
                (version {{ config('konta360.terms_version') }})
            </p>
            @if ($company->hasAcceptedCurrentTerms())
                <p class="mt-3 text-sm text-gray-500">Acceptées le {{ $company->terms_accepted_at?->format('d/m/Y') }}@if ($company->termsAcceptor) par {{ $company->termsAcceptor->name }}@endif.</p>
            @else
                <form method="POST" action="{{ route('administration.data.terms.accept') }}" class="mt-4">
                    @csrf
                    <p class="mb-3 text-sm text-amber-800">Cette version n’a pas encore été acceptée pour votre société.</p>
                    <x-primary-button>J’accepte au nom de la société</x-primary-button>
                </form>
            @endif
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-red-200 sm:p-8">
            <h2 class="text-lg font-semibold text-red-700">Clôturer le compte de la société</h2>
            <p class="mt-2 text-sm text-gray-600">
                À la clôture, plus aucun utilisateur ne peut se connecter. Les pièces comptables devant être conservées
                {{ config('konta360.retention_years') }} ans, les données sont gardées pendant cette durée puis supprimées définitivement.
                Téléchargez l’export ci-dessus avant de demander la clôture.
            </p>

            @if ($company->isClosureRequested())
                <div class="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                    Clôture demandée le {{ $company->closure_requested_at->format('d/m/Y') }}@if ($company->closureRequester) par {{ $company->closureRequester->name }}@endif. L’équipe Konta360 va la traiter.
                </div>
                <form method="POST" action="{{ route('administration.data.closure.cancel') }}" class="mt-4">
                    @csrf @method('DELETE')
                    <x-secondary-button type="submit">Annuler la demande</x-secondary-button>
                </form>
            @else
                <form method="POST" action="{{ route('administration.data.closure.request') }}" class="mt-4 grid gap-4 sm:grid-cols-2" onsubmit="return confirm('Demander la clôture définitive du compte de la société ?')">
                    @csrf
                    <div>
                        <x-input-label for="company_name" :value="'Saisissez « '.$company->name.' »'" />
                        <x-text-input id="company_name" name="company_name" class="mt-1 block w-full" autocomplete="off" required />
                        <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="password" value="Votre mot de passe" />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="current-password" required />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2"><x-danger-button>Demander la clôture</x-danger-button></div>
                </form>
            @endif
        </section>
    </div></div>
</x-app-layout>
