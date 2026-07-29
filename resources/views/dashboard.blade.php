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

            @canany([Permission::InvoicesView->value, Permission::AccountingView->value, Permission::FinancialStatementsView->value])
                <section class="mt-8">
                    <div class="flex items-end justify-between">
                        <div><p class="text-sm font-medium text-indigo-600">Vue financière</p><h2 class="mt-1 text-xl font-semibold text-gray-900">Situation actuelle par devise</h2></div>
                        <p class="text-xs text-gray-500">Mise à jour en temps réel</p>
                    </div>
                    <div class="mt-4 grid gap-5 xl:grid-cols-2">
                        @foreach ($metrics as $currency => $metric)
                            <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                                <div class="flex items-center justify-between"><h3 class="font-semibold text-gray-900">{{ $currency }}</h3>@if($metric['overdue_count'] > 0)<span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">{{ $metric['overdue_count'] }} facture(s) échue(s)</span>@endif</div>
                                <dl class="mt-5 grid grid-cols-2 gap-5 sm:grid-cols-4">
                                    <div><dt class="text-xs uppercase text-gray-500">Ventes nettes</dt><dd class="mt-1 text-lg font-semibold">{{ number_format($metric['sales'],2,',',' ') }}</dd></div>
                                    <div><dt class="text-xs uppercase text-gray-500">Encaissé</dt><dd class="mt-1 text-lg font-semibold text-emerald-600">{{ number_format($metric['collected'],2,',',' ') }}</dd></div>
                                    <div><dt class="text-xs uppercase text-gray-500">Dépenses</dt><dd class="mt-1 text-lg font-semibold text-amber-700">{{ number_format($metric['expenses'],2,',',' ') }}</dd></div>
                                    <div><dt class="text-xs uppercase text-gray-500">À encaisser</dt><dd class="mt-1 text-lg font-semibold text-indigo-700">{{ number_format($metric['outstanding'],2,',',' ') }}</dd></div>
                                </dl>
                                @if($metric['overdue_amount'] > 0)<p class="mt-4 border-t pt-3 text-sm text-red-700">Retards de paiement : <strong>{{ number_format($metric['overdue_amount'],2,',',' ') }} {{ $currency }}</strong></p>@endif
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="mt-8 grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                    <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div><h2 class="font-semibold text-gray-900">Ventes des six derniers mois</h2><p class="mt-1 text-sm text-gray-500">Factures validées, avant déduction des avoirs.</p></div>
                        <div class="mt-6 space-y-5">
                            @foreach($months as $month)
                                <div class="grid grid-cols-[80px_1fr] items-center gap-4">
                                    <span class="text-xs font-semibold text-gray-500">{{ $month['label'] }}</span>
                                    <div class="space-y-2">
                                        @foreach(['USD' => 'bg-indigo-500', 'CDF' => 'bg-emerald-500'] as $currency => $color)
                                            @php $width = max(1, ($month['totals'][$currency] / $maxMonthly[$currency]) * 100); @endphp
                                            <div class="flex items-center gap-3"><span class="w-8 text-xs font-medium">{{ $currency }}</span><div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ $color }}" style="width: {{ $width }}%"></div></div><span class="w-28 text-right text-xs font-semibold">{{ number_format($month['totals'][$currency],0,',',' ') }}</span></div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>

                    <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div class="flex items-center justify-between"><div><h2 class="font-semibold text-gray-900">Soldes de trésorerie</h2><p class="mt-1 text-sm text-gray-500">Banques et caisses actives.</p></div>@can(Permission::TreasuryManage->value)<a href="{{ route('treasury.index') }}" class="text-sm font-semibold text-indigo-600">Ouvrir</a>@endcan</div>
                        <div class="mt-5 divide-y divide-gray-100">
                            @forelse($treasury as $account)
                                <div class="flex items-center justify-between py-3"><div><p class="text-sm font-semibold">{{ $account->name }}</p><p class="text-xs text-gray-500">{{ $account->type->label() }}</p></div><p class="font-semibold {{ $account->balance() < 0 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format($account->balance(),2,',',' ') }} {{ $account->currency }}</p></div>
                            @empty
                                <p class="py-8 text-center text-sm text-gray-500">Aucun compte de trésorerie.</p>
                            @endforelse
                        </div>
                    </article>
                </section>
            @endcanany

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

                @can(Permission::AccountingView->value)
                    <a href="{{ route('expenses.index') }}" class="group rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div><p class="text-sm font-medium text-indigo-600">Achats</p><h3 class="mt-1 text-lg font-semibold text-gray-900">Dépenses fournisseurs</h3></div>
                            <span class="rounded-lg bg-indigo-50 px-3 py-2 text-indigo-600 group-hover:bg-indigo-100">→</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-gray-600">Enregistrer les charges, suivre les dettes et payer depuis la trésorerie.</p>
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
