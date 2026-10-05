@php
    $money = fn ($value, $currency) => number_format((float) $value, (float) $value == floor((float) $value) ? 0 : 2, ',', ' ').' '.$currency;
    // « Jusqu’à 5 utilisateurs actifs » or « Utilisateurs actifs illimités ».
    $limit = fn (?int $value, string $unit, string $unlimited) => $value === null ? $unlimited : "Jusqu’à {$value} {$unit}";
    $paidPlans = $plans->where('is_evaluation', false)->values();
    $evaluation = $plans->firstWhere('is_evaluation', true);
    $features = [
        'Ventes' => ['Devis, conversion en facture', 'Factures avec avances et retenues', 'Avoirs', 'Règlements clients et suivi des impayés'],
        'Comptabilité' => ['Écritures automatiques et manuelles', 'Grand livre et balance', 'Compte de résultat et bilan', 'Périodes et clôture d’exercice'],
        'Trésorerie' => ['Comptes bancaires et caisses', 'Mouvements et virements internes', 'Rapprochement bancaire', 'Dépenses fournisseurs et leurs paiements'],
        'Gestion' => ['Clients, fournisseurs et catalogue', 'Immobilisations et amortissements', 'Tableau de bord financier', 'Journal d’audit de toutes les opérations'],
        'Documents' => ['PDF avec QR code de vérification', 'Documents en français ou en anglais', 'Montants en CDF ou en USD', 'Exports CSV et export complet de vos données'],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Formules et tarifs — Konta360</title>
    <meta name="description" content="Comparez les formules Konta360 : évaluation gratuite d’un mois, puis Essentiel, Pro ou Entreprise.">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ url('/') }}" class="text-lg font-bold">Konta360</a>
            <nav class="flex items-center gap-4 text-sm font-medium">
                @auth
                    <a href="{{ route('subscription.show') }}" class="text-indigo-600 hover:text-indigo-500">Mon abonnement</a>
                @else
                    <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900">Se connecter</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-500">Créer votre espace</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl space-y-16 px-4 py-12 sm:px-6">
        <section class="text-center">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Formules et tarifs</h1>
            <p class="mx-auto mt-4 max-w-2xl text-gray-600">
                <strong>Toutes les formules donnent accès à tous les modules de Konta360.</strong>
                Elles ne diffèrent que par la taille de votre activité : le nombre de personnes qui travaillent dans l’application
                et le nombre de factures que vous validez chaque mois.
            </p>
        </section>

        {{-- Plan cards --}}
        <section class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            @foreach ($plans as $plan)
                <div class="flex flex-col rounded-2xl bg-white p-6 shadow-sm ring-1 {{ $plan->is_evaluation ? 'ring-emerald-300' : 'ring-gray-200' }}">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">{{ $plan->name }}</h2>
                        @if ($plan->is_evaluation)
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Pour commencer</span>
                        @endif
                    </div>
                    <p class="mt-2 min-h-[3rem] text-sm text-gray-600">{{ $plan->description }}</p>
                    <p class="mt-4">
                        @if ($plan->is_evaluation)
                            <span class="text-3xl font-bold">Gratuit</span>
                            <span class="block text-sm text-gray-500">pendant {{ config('konta360.billing.trial_days') }} jours</span>
                        @else
                            <span class="text-3xl font-bold">{{ $money($plan->monthly_price, $plan->currency) }}</span>
                            <span class="text-sm text-gray-500">/ mois</span>
                        @endif
                    </p>
                    <ul class="mt-6 flex-1 space-y-2 text-sm">
                        <li class="flex gap-2"><span class="text-indigo-600">●</span>{{ $limit($plan->max_users, 'utilisateurs actifs', 'Utilisateurs actifs illimités') }}</li>
                        <li class="flex gap-2"><span class="text-indigo-600">●</span>{{ $limit($plan->max_invoices_per_month, 'factures validées par mois', 'Factures validées illimitées') }}</li>
                        <li class="flex gap-2"><span class="text-indigo-600">●</span>Tous les modules inclus</li>
                        <li class="flex gap-2"><span class="text-indigo-600">●</span>{{ $plan->is_evaluation ? 'Sans moyen de paiement, sans engagement' : 'Payable pour 1, 3, 6 ou 12 mois' }}</li>
                    </ul>
                    @guest
                        @if ($plan->is_evaluation)
                            <a href="{{ route('register') }}" class="mt-6 block rounded-md bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-500">Commencer l’évaluation</a>
                        @endif
                    @endguest
                </div>
            @endforeach
        </section>

        {{-- Comparison table --}}
        <section>
            <h2 class="text-2xl font-bold">Comparaison détaillée</h2>
            <div class="mt-6 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left">
                            <th class="px-4 py-4 font-medium text-gray-500"></th>
                            @foreach ($plans as $plan)
                                <th class="px-4 py-4 text-center font-semibold">{{ $plan->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="px-4 py-3 font-medium">Prix</td>
                            @foreach ($plans as $plan)
                                <td class="px-4 py-3 text-center">{{ $plan->is_evaluation ? 'Gratuit' : $money($plan->monthly_price, $plan->currency).' / mois' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium">Durée</td>
                            @foreach ($plans as $plan)
                                <td class="px-4 py-3 text-center">{{ $plan->is_evaluation ? config('konta360.billing.trial_days').' jours, une seule fois' : '1, 3, 6 ou 12 mois, renouvelable' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium">Utilisateurs actifs</td>
                            @foreach ($plans as $plan)
                                <td class="px-4 py-3 text-center">{{ $plan->max_users ?? 'Illimité' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium">Factures validées par mois</td>
                            @foreach ($plans as $plan)
                                <td class="px-4 py-3 text-center">{{ $plan->max_invoices_per_month ?? 'Illimité' }}</td>
                            @endforeach
                        </tr>
                        @foreach ($features as $group => $items)
                            <tr class="bg-gray-50"><td colspan="{{ $plans->count() + 1 }}" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $group }}</td></tr>
                            @foreach ($items as $feature)
                                <tr>
                                    <td class="px-4 py-3">{{ $feature }}</td>
                                    @foreach ($plans as $plan)
                                        <td class="px-4 py-3 text-center text-indigo-600" aria-label="Inclus">✓</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- How the limits work --}}
        <section class="grid gap-6 md:grid-cols-2">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h2 class="text-lg font-semibold">Qu’est-ce qu’un utilisateur actif ?</h2>
                <p class="mt-3 text-sm text-gray-600">
                    Chaque personne qui se connecte à l’espace de votre société avec son propre compte : administrateur, comptable,
                    direction ou commercial. Un compte <strong>désactivé</strong> ne compte plus : vous pouvez désactiver le compte d’une
                    personne qui quitte la société pour en créer un nouveau.
                </p>
                <p class="mt-3 text-sm text-gray-600">Une fois la limite atteinte, la création ou la réactivation d’un compte est refusée, avec un message explicite.</p>
            </div>
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h2 class="text-lg font-semibold">Quelles factures sont comptées ?</h2>
                <p class="mt-3 text-sm text-gray-600">
                    Seules les <strong>factures validées</strong> (numérotées) au cours du mois civil, du 1<sup>er</sup> au dernier jour.
                    Le compteur repart à zéro chaque mois.
                </p>
                <p class="mt-3 text-sm text-gray-600">
                    Ne sont <strong>jamais limités</strong> : les factures brouillon, les devis, les avoirs, les règlements, les dépenses,
                    les écritures comptables et les mouvements de trésorerie. Une fois la limite atteinte, vous pouvez toujours préparer
                    vos factures en brouillon et les valider le mois suivant ou après un changement de formule.
                </p>
            </div>
        </section>

        {{-- Lifecycle --}}
        <section>
            <h2 class="text-2xl font-bold">Comment ça se passe</h2>
            <ol class="mt-6 grid gap-6 md:grid-cols-4">
                <li class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-semibold text-indigo-600">1. Évaluation</p>
                    <p class="mt-2 text-sm text-gray-600">
                        À l’inscription, votre société dispose de {{ config('konta360.billing.trial_days') }} jours gratuits
                        @if ($evaluation)
                            ({{ strtolower($limit($evaluation->max_users, 'utilisateurs', 'utilisateurs illimités')) }}, {{ strtolower($limit($evaluation->max_invoices_per_month, 'factures validées par mois', 'factures illimitées')) }})
                        @endif
                        pour tout essayer, sans moyen de paiement.
                    </p>
                </li>
                <li class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-semibold text-indigo-600">2. Choix et paiement</p>
                    <p class="mt-2 text-sm text-gray-600">
                        Choisissez une formule et une durée ({{ implode(', ', $durations) }} mois), payez par Mobile Money ou virement,
                        puis déclarez la référence du paiement dans <em>Abonnement</em>. Le montant est le prix mensuel multiplié par le nombre de mois.
                    </p>
                </li>
                <li class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-semibold text-indigo-600">3. Confirmation</p>
                    <p class="mt-2 text-sm text-gray-600">
                        L’équipe Konta360 vérifie le paiement et active la période. Elle commence <strong>après</strong> les jours
                        d’évaluation ou d’abonnement qu’il vous reste : payer en avance ne vous fait perdre aucun jour.
                    </p>
                </li>
                <li class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-semibold text-indigo-600">4. Renouvellement</p>
                    <p class="mt-2 text-sm text-gray-600">
                        Un bandeau vous prévient 7 jours avant la fin. Sans renouvellement, votre espace passe en
                        <strong>lecture seule</strong> : vous consultez et exportez toujours vos données, mais ne pouvez plus en créer.
                    </p>
                </li>
            </ol>
        </section>

        {{-- FAQ --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <h2 class="text-2xl font-bold">Questions fréquentes</h2>
            <dl class="mt-6 space-y-6 text-sm">
                <div>
                    <dt class="font-semibold">Quelle formule choisir ?</dt>
                    <dd class="mt-1 text-gray-600">
                        @foreach ($paidPlans as $plan)
                            <strong>{{ $plan->name }}</strong> : {{ $limit($plan->max_users, 'utilisateurs', 'utilisateurs illimités') }}, {{ strtolower($limit($plan->max_invoices_per_month, 'factures validées par mois', 'Factures validées illimitées')) }}.
                        @endforeach
                        Partez du nombre de personnes qui travailleront dans l’application et du nombre de factures que vous émettez un mois chargé.
                    </dd>
                </div>
                <div>
                    <dt class="font-semibold">Puis-je changer de formule ?</dt>
                    <dd class="mt-1 text-gray-600">Oui, à chaque paiement : la nouvelle formule s’applique dès la confirmation du paiement. Il n’y a pas de calcul au prorata. Passer à une formule plus petite ne désactive aucun compte, mais empêche d’en ajouter tant que vous dépassez la limite.</dd>
                </div>
                <div>
                    <dt class="font-semibold">Payer plusieurs mois d’avance est-il moins cher ?</dt>
                    <dd class="mt-1 text-gray-600">Non : le prix est le même quelle que soit la durée. Une durée plus longue vous évite simplement de renouveler souvent.</dd>
                </div>
                <div>
                    <dt class="font-semibold">Que deviennent mes données si j’arrête ?</dt>
                    <dd class="mt-1 text-gray-600">Elles restent consultables en lecture seule et exportables à tout moment (archive ZIP au format CSV). Si vous demandez la clôture du compte, elles sont conservées {{ config('konta360.retention_years') }} ans, comme l’exige le droit comptable, puis supprimées définitivement.</dd>
                </div>
                <div>
                    <dt class="font-semibold">Mes données sont-elles séparées de celles des autres sociétés ?</dt>
                    <dd class="mt-1 text-gray-600">Oui. Chaque société a son espace, ses utilisateurs et sa comptabilité ; aucune autre société ne peut y accéder. Voir la <a href="{{ route('legal.privacy') }}" class="font-medium text-indigo-600">politique de confidentialité</a>.</dd>
                </div>
            </dl>
        </section>

        <p class="text-center text-xs text-gray-500">Voir les <a href="{{ route('legal.terms') }}" class="underline">conditions générales d’utilisation</a>.</p>
    </main>
</body>
</html>
