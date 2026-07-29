@php use App\Modules\Administration\Enums\Permission; @endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">Konta360</p>
            <h1 class="text-2xl font-semibold text-gray-900">Tableau de bord</h1>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-gradient-to-br from-indigo-700 to-indigo-950 p-8 text-white shadow-lg">
                <p class="text-sm font-medium text-indigo-200">Bienvenue, {{ auth()->user()->name }}</p>
                <h2 class="mt-2 max-w-2xl text-3xl font-semibold">Pilotez votre activité depuis un espace unique.</h2>
                <p class="mt-3 max-w-2xl text-indigo-100">Commencez par structurer vos clients et fournisseurs. Ils serviront ensuite aux devis, factures, paiements et écritures.</p>
            </div>

            <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @can(Permission::PartiesManage->value)
                    <a href="{{ route('parties.index') }}" class="group rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-indigo-600">Référentiel</p>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900">Clients et fournisseurs</h3>
                            </div>
                            <span class="rounded-lg bg-indigo-50 px-3 py-2 text-indigo-600 group-hover:bg-indigo-100">→</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-gray-600">Créer, rechercher et mettre à jour les tiers de l’entreprise.</p>
                    </a>
                @endcan

                @can(Permission::CatalogManage->value)
                    <a href="{{ route('catalog.index') }}" class="group rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-indigo-600">Facturation</p>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900">Produits et services</h3>
                            </div>
                            <span class="rounded-lg bg-indigo-50 px-3 py-2 text-indigo-600 group-hover:bg-indigo-100">→</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-gray-600">Gérer les références, prix, devises et taux de taxe du catalogue.</p>
                    </a>
                @endcan

                @can(Permission::QuotesView->value)
                    <a href="{{ route('quotes.index') }}" class="group rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div><p class="text-sm font-medium text-indigo-600">Ventes</p><h3 class="mt-1 text-lg font-semibold text-gray-900">Devis</h3></div>
                            <span class="rounded-lg bg-indigo-50 px-3 py-2 text-indigo-600 group-hover:bg-indigo-100">→</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-gray-600">Préparer et suivre les propositions commerciales avec calcul automatique.</p>
                    </a>
                @endcan

                @can(Permission::InvoicesView->value)
                    <a href="{{ route('invoices.index') }}" class="group rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div><p class="text-sm font-medium text-indigo-600">Ventes</p><h3 class="mt-1 text-lg font-semibold text-gray-900">Factures</h3></div>
                            <span class="rounded-lg bg-indigo-50 px-3 py-2 text-indigo-600 group-hover:bg-indigo-100">→</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-gray-600">Valider les factures issues des devis et suivre leurs échéances.</p>
                    </a>
                @endcan

                @canany([Permission::PaymentsRecord->value, Permission::PaymentsReverse->value])
                    <a href="{{ route('payments.index') }}" class="group rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div><p class="text-sm font-medium text-indigo-600">Trésorerie</p><h3 class="mt-1 text-lg font-semibold text-gray-900">Règlements</h3></div>
                            <span class="rounded-lg bg-indigo-50 px-3 py-2 text-indigo-600 group-hover:bg-indigo-100">→</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-gray-600">Enregistrer les paiements et suivre les soldes restant dus.</p>
                    </a>
                @endcanany

                @can(Permission::AccountingView->value)
                    <a href="{{ route('accounting.entries.index') }}" class="group rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div><p class="text-sm font-medium text-indigo-600">Comptabilité</p><h3 class="mt-1 text-lg font-semibold text-gray-900">Journal des écritures</h3></div>
                            <span class="rounded-lg bg-indigo-50 px-3 py-2 text-indigo-600 group-hover:bg-indigo-100">→</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-gray-600">Consulter les écritures équilibrées générées par les ventes et règlements.</p>
                    </a>
                @endcan

                @can(Permission::TreasuryManage->value)
                    <a href="{{ route('treasury.index') }}" class="group rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div><p class="text-sm font-medium text-indigo-600">Finance</p><h3 class="mt-1 text-lg font-semibold text-gray-900">Trésorerie</h3></div>
                            <span class="rounded-lg bg-indigo-50 px-3 py-2 text-indigo-600 group-hover:bg-indigo-100">→</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-gray-600">Suivre les banques, les caisses, les soldes et les mouvements internes.</p>
                    </a>
                @endcan

                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6">
                    <p class="text-sm font-medium text-gray-500">Patrimoine</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900">Immobilisations</h3>
                    @can(Permission::FixedAssetsManage->value)
                        <p class="mt-3 text-sm leading-6 text-gray-600"><a href="{{ route('fixed-assets.index') }}" class="font-semibold text-indigo-600">Gérer les biens</a> et consulter leurs plans d’amortissement.</p>
                    @else
                        <p class="mt-3 text-sm leading-6 text-gray-600">Le registre des immobilisations est réservé aux utilisateurs autorisés.</p>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
