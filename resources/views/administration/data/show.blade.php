<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Administration</p><h1 class="text-2xl font-semibold text-gray-900">Données et confidentialité</h1><p class="mt-1 text-sm text-gray-500">Export de vos données, conditions d’utilisation et clôture du compte.</p></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if (session('success'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <h2 class="text-lg font-semibold">Exporter toutes vos données</h2>
            <p class="mt-2 text-sm text-gray-600">Une archive ZIP avec une feuille CSV par table (tiers, factures, écritures, trésorerie…), vos utilisateurs et vos fichiers (logo, signature, cachet). Vous pouvez l’ouvrir avec un tableur et la conserver indépendamment de Konta360.</p>
            <a href="{{ route('administration.data.export') }}" class="mt-4 inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Télécharger l’export</a>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-amber-200 sm:p-8">
            @if ($company->real_started_at)
                <h2 class="text-lg font-semibold text-emerald-800">Activité réelle démarrée</h2>
                <p class="mt-2 text-sm text-gray-600">Démarrage enregistré le {{ $company->real_started_at->format('d/m/Y à H:i') }}, avec une date de début au {{ $company->real_start_date?->format('d/m/Y') }}.</p>
                @if ($realStartArchivePath)
                    <a href="{{ route('administration.data.real-start.archive') }}" class="mt-4 inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Télécharger l’archive des essais</a>
                @else
                    <p class="mt-3 text-sm text-red-700">L’archive n’est pas disponible. Contactez l’assistance avant de modifier les données de la société.</p>
                @endif
            @else
                <h2 class="text-lg font-semibold text-amber-950">Démarrer en réel</h2>
                <p class="mt-2 text-sm text-gray-600">Préparez cette société pour sa première période d’activité réelle. Les paramètres, utilisateurs, clients, fournisseurs, articles et plan comptable seront conservés.</p>

                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                    <p class="font-semibold">Cette opération remplace les données d’essai dans l’application.</p>
                    <p class="mt-1">Avant le nettoyage, une archive ZIP privée sera créée avec les données actuelles. Les devis, factures, avoirs, règlements, dépenses, écritures, rapprochements et immobilisations seront retirés des écrans opérationnels après archivage. Les numéros de documents ne seront pas réutilisés.</p>
                    <p class="mt-2">Si une facture ou une écriture a déjà été officiellement émise ou comptabilisée, vérifiez d’abord avec votre comptable et ne la traitez pas comme une donnée d’essai.</p>
                    <p class="mt-2">Cette étape ne modifie pas votre abonnement. Elle définit seulement les soldes de départ de la trésorerie ; les soldes clients, fournisseurs et autres comptes doivent être préparés dans la comptabilité avec votre comptable.</p>
                </div>

                <div class="mt-5 rounded-lg border border-gray-200 p-4">
                    <button type="button" data-collapse-trigger data-target="#real-start-preview" aria-controls="real-start-preview" aria-expanded="true" class="group flex w-full items-center justify-between gap-4 text-left font-semibold text-gray-900">
                        <span>Données qui seront archivées puis nettoyées</span>
                        <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform group-aria-expanded:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                    </button>
                    <div id="real-start-preview" data-fx-collapse data-state="open" class="overflow-hidden transition-[height] duration-300 ease-in-out">
                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($realStartPreview as $label => $count)
                                <div class="flex justify-between gap-3 rounded bg-gray-50 px-3 py-2"><dt class="text-gray-600">{{ ucfirst($label) }}</dt><dd class="font-semibold text-gray-900">{{ $count }}</dd></div>
                            @endforeach
                        </dl>
                    </div>
                </div>

                <form method="POST" action="{{ route('administration.data.real-start') }}" class="mt-6 space-y-6">
                    @csrf
                    <div>
                        <x-input-label for="start_date" value="Date de début de l’activité réelle *" />
                        <x-text-input id="start_date" name="start_date" type="date" max="{{ today()->toDateString() }}" class="mt-1 block w-full sm:max-w-xs" :value="old('start_date', today()->toDateString())" required />
                        <p class="mt-1 text-xs text-gray-500">Les soldes de trésorerie ci-dessous correspondent à cette date.</p>
                        <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                    </div>

                    @if ($treasuryAccounts->isNotEmpty())
                        <div>
                            <h3 class="font-semibold text-gray-900">Comptes de trésorerie à conserver</h3>
                            <p class="mt-1 text-sm text-gray-600">Cochez les comptes réels, vérifiez leur nom, leur type et leur devise, puis saisissez leur solde d’ouverture. Les comptes décochés seront retirés.</p>
                            <div class="mt-3 space-y-3">
                                @foreach ($treasuryAccounts as $account)
                                    <fieldset class="rounded-lg border border-gray-200 p-4">
                                        <legend class="px-1 text-sm font-semibold text-gray-700">{{ $account->name }} · {{ $account->currency }}</legend>
                                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                                <input type="hidden" name="accounts[{{ $account->id }}][keep]" value="0">
                                                <input type="checkbox" name="accounts[{{ $account->id }}][keep]" value="1" @checked(old("accounts.{$account->id}.keep", '1') === '1') class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                Garder ce compte
                                            </label>
                                            <div><x-input-label :for="'account-'.$account->id.'-name'" value="Nom du compte" /><x-text-input :id="'account-'.$account->id.'-name'" :name="'accounts['.$account->id.'][name]'" class="mt-1 block w-full" :value="old('accounts.'.$account->id.'.name', $account->name)" /><x-input-error :messages="$errors->get('accounts.'.$account->id.'.name')" class="mt-1" /></div>
                                            <div><x-input-label :for="'account-'.$account->id.'-type'" value="Type" /><select id="account-{{ $account->id }}-type" name="accounts[{{ $account->id }}][type]" class="mt-1 block w-full rounded-md border-gray-300"><option value="cash" @selected(old('accounts.'.$account->id.'.type', $account->type->value) === 'cash')>Caisse</option><option value="bank" @selected(old('accounts.'.$account->id.'.type', $account->type->value) === 'bank')>Banque</option></select></div>
                                            <div><x-input-label :for="'account-'.$account->id.'-currency'" value="Devise" /><select id="account-{{ $account->id }}-currency" name="accounts[{{ $account->id }}][currency]" class="mt-1 block w-full rounded-md border-gray-300"><option value="CDF" @selected(old('accounts.'.$account->id.'.currency', $account->currency) === 'CDF')>CDF</option><option value="USD" @selected(old('accounts.'.$account->id.'.currency', $account->currency) === 'USD')>USD</option></select></div>
                                            <div><x-input-label :for="'account-'.$account->id.'-balance'" value="Solde d’ouverture *" /><x-text-input :id="'account-'.$account->id.'-balance'" :name="'accounts['.$account->id.'][opening_balance]'" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('accounts.'.$account->id.'.opening_balance')" placeholder="0,00" /><x-input-error :messages="$errors->get('accounts.'.$account->id.'.opening_balance')" class="mt-1" /></div>
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('accounts')" class="mt-2" />
                        </div>
                    @else
                        <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-600">Aucun compte de trésorerie n’existe encore. Vous pourrez créer vos comptes réels après le démarrage.</p>
                    @endif

                    <label class="flex items-start gap-3 rounded-lg bg-amber-50 p-4 text-sm text-amber-950">
                        <input type="checkbox" name="archive_confirmed" value="1" @checked(old('archive_confirmed')) class="mt-0.5 rounded border-amber-400 text-amber-700 focus:ring-amber-600" required>
                        <span>Je comprends que les données de test seront retirées des écrans après la création de l’archive, et que je dois télécharger et conserver cette archive.</span>
                    </label>
                    <x-input-error :messages="$errors->get('archive_confirmed')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="company_name" :value="'Confirmez le nom exact : '.$company->name" />
                            <x-text-input id="company_name" name="company_name" class="mt-1 block w-full" autocomplete="off" :value="old('company_name')" required />
                            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="real_start_password" value="Votre mot de passe *" />
                            <x-text-input id="real_start_password" name="password" type="password" class="mt-1 block w-full" autocomplete="current-password" required />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                    </div>
                    <div><x-danger-button>Démarrer en réel et archiver les essais</x-danger-button></div>
                </form>
            @endif
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
