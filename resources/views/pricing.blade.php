@php
    $money = fn ($value, $currency) => number_format((float) $value, (float) $value == floor((float) $value) ? 0 : 2, ',', ' ').' '.$currency;
    // « Jusqu’à 5 utilisateurs actifs » or « Utilisateurs actifs illimités ».
    $limit = fn (?int $value, string $unit, string $unlimited) => $value === null ? $unlimited : "Jusqu’à {$value} {$unit}";
    $paidPlans = $plans->where('is_evaluation', false)->values();
    $evaluation = $plans->firstWhere('is_evaluation', true);
    $trialDays = config('konta360.billing.trial_days');
    $finderPlans = $paidPlans->map(fn ($plan) => [
        'name' => $plan->name,
        'price' => $money($plan->monthly_price, $plan->currency),
        'users' => $plan->max_users,
        'invoices' => $plan->max_invoices_per_month,
    ])->values();
    $modules = [
        ['Ventes', 'M3 7h18M3 12h18M3 17h12', ['Devis et conversion en facture', 'Factures avec avances et retenues', 'Avoirs', 'Règlements et suivi des impayés']],
        ['Comptabilité', 'M4 19V5m0 14h16M8 15l3-4 3 2 5-6', ['Écritures automatiques et manuelles', 'Grand livre et balance', 'Compte de résultat et bilan', 'Périodes et clôture d’exercice']],
        ['Trésorerie', 'M3 10h18M5 10V7l7-4 7 4v3M6 10v7m4-7v7m4-7v7m4-7v7M4 21h16', ['Comptes bancaires et caisses', 'Mouvements et virements internes', 'Rapprochement bancaire', 'Dépenses fournisseurs']],
        ['Gestion', 'M4 6h16M4 12h16M4 18h7', ['Clients, fournisseurs et catalogue', 'Immobilisations et amortissements', 'Tableau de bord financier', 'Journal d’audit complet']],
        ['Documents', 'M7 3h7l5 5v13H7zM14 3v5h5', ['PDF avec QR code de vérification', 'Documents en français ou en anglais', 'Montants en CDF ou en USD', 'Exports CSV et export complet']],
        ['Équipe et sécurité', 'M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z', ['Rôles : administrateur, comptable, direction, commercial', 'Données isolées par société', 'Vérification de l’adresse e-mail', 'Clôture et conservation légale']],
    ];
    $faq = [
        ['Puis-je changer de formule ?', 'Oui, à chaque paiement : la nouvelle formule s’applique dès la confirmation du paiement, sans calcul au prorata. Passer à une formule plus petite ne désactive aucun compte, mais empêche d’en ajouter tant que vous dépassez la limite.'],
        ['Payer plusieurs mois d’avance est-il moins cher ?', 'Non : le prix mensuel est le même quelle que soit la durée. Une durée plus longue vous évite simplement de renouveler souvent.'],
        ['Que se passe-t-il à la fin de l’évaluation si je ne paie pas ?', 'Votre espace passe en lecture seule : vous consultez et exportez toujours vos données, mais ne pouvez plus en créer. Il redevient complet dès la confirmation de votre paiement.'],
        ['Que deviennent mes données si j’arrête ?', 'Elles restent exportables à tout moment (archive ZIP au format CSV). Si vous demandez la clôture du compte, elles sont conservées '.config('konta360.retention_years').' ans, comme l’exige le droit comptable, puis supprimées définitivement.'],
        ['Mes données sont-elles séparées de celles des autres sociétés ?', 'Oui. Chaque société a son espace, ses utilisateurs et sa comptabilité ; aucune autre société ne peut y accéder.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Formules et tarifs — Konta360</title>
    <meta name="description" content="Comparez les formules Konta360 : évaluation gratuite d’un mois, puis Essentiel, Pro ou Entreprise.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
    {{-- Hero --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-indigo-700 via-indigo-600 to-violet-600 pb-40 text-white">
        <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/10"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -left-24 h-[28rem] w-[28rem] rounded-full bg-white/5"></div>

        <header class="relative mx-auto flex max-w-6xl items-center justify-between px-4 py-6 sm:px-6">
            <a href="{{ url('/') }}"><x-brand inverted /></a>
            <nav class="flex items-center gap-3 text-sm font-medium sm:gap-5">
                @auth
                    <a href="{{ route('subscription.show') }}" class="rounded-md bg-white px-4 py-2 text-indigo-700 hover:bg-indigo-50">Mon abonnement</a>
                @else
                    <a href="{{ route('login') }}" class="text-indigo-100 hover:text-white">Se connecter</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-white px-4 py-2 text-indigo-700 hover:bg-indigo-50">Créer votre espace</a>
                @endauth
            </nav>
        </header>

        <section class="relative mx-auto max-w-3xl px-4 pt-10 text-center sm:px-6">
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">Formules et tarifs</h1>
            <p class="mt-6 text-lg text-indigo-100">
                <strong class="text-white">Toutes les formules donnent accès à tous les modules de Konta360.</strong>
                Elles ne diffèrent que par la taille de votre activité : le nombre de personnes qui travaillent dans
                l’application et le nombre de factures que vous validez chaque mois.
            </p>
            <p class="mt-6 inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-sm">
                <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                {{ $trialDays }} jours d’évaluation gratuite, sans moyen de paiement
            </p>
        </section>
    </div>

    <main class="relative mx-auto -mt-28 max-w-6xl space-y-24 px-4 pb-20 sm:px-6">
        {{-- Plan cards --}}
        <section class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            @foreach ($plans as $plan)
                <div @class([
                    'flex flex-col rounded-2xl bg-white p-6 shadow-lg ring-1',
                    'ring-2 ring-emerald-400' => $plan->is_evaluation,
                    'ring-gray-200' => ! $plan->is_evaluation,
                ])>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold">{{ $plan->name }}</h2>
                        @if ($plan->is_evaluation)
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Pour commencer</span>
                        @endif
                    </div>
                    <p class="mt-2 min-h-[3rem] text-sm text-gray-600">{{ $plan->description }}</p>

                    <div class="mt-6 border-b border-gray-100 pb-6">
                        @if ($plan->is_evaluation)
                            <p class="text-4xl font-bold tracking-tight">Gratuit</p>
                            <p class="mt-1 text-sm text-gray-500">pendant {{ $trialDays }} jours</p>
                        @else
                            <p><span class="text-4xl font-bold tracking-tight">{{ $money($plan->monthly_price, $plan->currency) }}</span></p>
                            <p class="mt-1 text-sm text-gray-500">par mois</p>
                        @endif
                    </div>

                    <ul class="mt-6 flex-1 space-y-3 text-sm">
                        @foreach ([
                            ['M16 19v-1a4 4 0 0 0-8 0v1m4-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z', $limit($plan->max_users, 'utilisateurs actifs', 'Utilisateurs actifs illimités')],
                            ['M7 3h7l5 5v13H7zM14 3v5h5M10 13h6m-6 4h4', $limit($plan->max_invoices_per_month, 'factures validées par mois', 'Factures validées illimitées')],
                            ['M5 13l4 4L19 7', 'Tous les modules inclus'],
                        ] as [$icon, $text])
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                                <span>{{ $text }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @auth
                        @unless ($plan->is_evaluation)
                            <a href="{{ route('subscription.show') }}" class="mt-8 block rounded-lg border border-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-indigo-700 hover:bg-indigo-50">Choisir cette formule</a>
                        @endunless
                    @else
                        <a href="{{ route('register') }}" @class([
                            'mt-8 block rounded-lg px-4 py-2.5 text-center text-sm font-semibold',
                            'bg-indigo-600 text-white hover:bg-indigo-500' => $plan->is_evaluation,
                            'border border-gray-300 text-gray-700 hover:border-gray-400 hover:bg-gray-50' => ! $plan->is_evaluation,
                        ])>{{ $plan->is_evaluation ? 'Commencer l’évaluation' : 'Essayer d’abord gratuitement' }}</a>
                    @endauth
                </div>
            @endforeach
        </section>

        {{-- Plan finder --}}
        @if ($paidPlans->isNotEmpty())
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-10">
                <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                    <div>
                        <h2 class="text-2xl font-bold">Quelle formule pour votre société ?</h2>
                        <p class="mt-3 text-gray-600">Indiquez combien de personnes utiliseront Konta360 et combien de factures vous validez un mois chargé : nous vous indiquons la formule la moins chère qui suffit.</p>
                        <div class="mt-8 space-y-6">
                            <label class="block">
                                <span class="flex justify-between text-sm font-medium"><span>Utilisateurs</span><output id="finder-users-value" class="font-semibold text-indigo-700">2</output></span>
                                <input id="finder-users" type="range" min="1" max="20" value="2" class="mt-2 w-full accent-indigo-600">
                            </label>
                            <label class="block">
                                <span class="flex justify-between text-sm font-medium"><span>Factures validées par mois</span><output id="finder-invoices-value" class="font-semibold text-indigo-700">40</output></span>
                                <input id="finder-invoices" type="range" min="5" max="1000" step="5" value="40" class="mt-2 w-full accent-indigo-600">
                            </label>
                        </div>
                    </div>
                    <div class="rounded-2xl bg-indigo-50 p-8 text-center" aria-live="polite">
                        <p class="text-sm font-medium text-indigo-700">Formule conseillée</p>
                        <p id="finder-plan" class="mt-2 text-4xl font-bold text-gray-900">—</p>
                        <p id="finder-price" class="mt-2 text-lg text-gray-700"></p>
                        <p id="finder-note" class="mt-4 text-sm text-gray-600"></p>
                    </div>
                </div>
            </section>
        @endif

        {{-- What differs --}}
        <section>
            <div class="max-w-2xl">
                <h2 class="text-3xl font-bold tracking-tight">Ce qui change d’une formule à l’autre</h2>
                <p class="mt-3 text-gray-600">Uniquement le prix et ces deux limites.</p>
            </div>
            <div class="mt-8 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left">
                            <th class="px-6 py-4 font-medium text-gray-500"></th>
                            @foreach ($plans as $plan)
                                <th class="px-6 py-4 text-center font-semibold">{{ $plan->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="px-6 py-4 font-medium">Prix</td>
                            @foreach ($plans as $plan)
                                <td class="px-6 py-4 text-center">{{ $plan->is_evaluation ? 'Gratuit' : $money($plan->monthly_price, $plan->currency).' / mois' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium">Durée</td>
                            @foreach ($plans as $plan)
                                <td class="px-6 py-4 text-center">{{ $plan->is_evaluation ? $trialDays.' jours, une seule fois' : implode(', ', $durations).' mois, renouvelable' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium">Utilisateurs actifs</td>
                            @foreach ($plans as $plan)
                                <td class="px-6 py-4 text-center text-base font-semibold">{{ $plan->max_users ?? '∞' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium">Factures validées par mois</td>
                            @foreach ($plans as $plan)
                                <td class="px-6 py-4 text-center text-base font-semibold">{{ $plan->max_invoices_per_month ?? '∞' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium">Modules</td>
                            @foreach ($plans as $plan)
                                <td class="px-6 py-4 text-center text-indigo-700">Tous</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="font-semibold">Qu’est-ce qu’un utilisateur actif ?</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Chaque personne qui se connecte avec son propre compte : administrateur, comptable, direction ou commercial.
                        Un compte <strong>désactivé</strong> ne compte plus : désactivez celui d’une personne qui quitte la société
                        pour en créer un nouveau.
                    </p>
                </div>
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h3 class="font-semibold">Quelles factures sont comptées ?</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Seules les <strong>factures validées</strong> (numérotées) du mois civil ; le compteur repart à zéro le 1<sup>er</sup>.
                        Brouillons, devis, avoirs, règlements, dépenses et écritures ne sont <strong>jamais limités</strong> :
                        à la limite, préparez vos factures en brouillon et validez-les le mois suivant ou après un changement de formule.
                    </p>
                </div>
            </div>
        </section>

        {{-- Modules --}}
        <section>
            <div class="max-w-2xl">
                <h2 class="text-3xl font-bold tracking-tight">Inclus dans toutes les formules</h2>
                <p class="mt-3 text-gray-600">Dès l’évaluation, vous travaillez avec l’application complète.</p>
            </div>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($modules as [$title, $icon, $items])
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                            </span>
                            <h3 class="font-semibold">{{ $title }}</h3>
                        </div>
                        <ul class="mt-4 space-y-2 text-sm text-gray-600">
                            @foreach ($items as $item)
                                <li class="flex gap-2"><span class="text-indigo-500">✓</span>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Lifecycle --}}
        <section>
            <h2 class="text-3xl font-bold tracking-tight">Comment ça se passe</h2>
            <ol class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Évaluation', "À l’inscription, {$trialDays} jours gratuits"
                        .($evaluation ? ' ('.mb_strtolower($limit($evaluation->max_users, 'utilisateurs', 'utilisateurs illimités')).', '.mb_strtolower($limit($evaluation->max_invoices_per_month, 'factures validées par mois', 'factures illimitées')).')' : '')
                        .', sans moyen de paiement.'],
                    ['Choix et paiement', 'Choisissez une formule et une durée, payez par Mobile Money ou virement, puis déclarez la référence dans Abonnement.'],
                    ['Confirmation', 'L’équipe Konta360 vérifie le paiement et active la période, qui commence après vos jours restants : rien n’est perdu.'],
                    ['Renouvellement', 'Un bandeau vous prévient 7 jours avant la fin. Sans renouvellement, votre espace passe en lecture seule.'],
                ] as $index => [$title, $text])
                    <li class="relative rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">{{ $index + 1 }}</span>
                        <p class="mt-4 font-semibold">{{ $title }}</p>
                        <p class="mt-2 text-sm text-gray-600">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- FAQ --}}
        <section class="mx-auto max-w-3xl">
            <h2 class="text-center text-3xl font-bold tracking-tight">Questions fréquentes</h2>
            <div class="mt-8 divide-y divide-gray-200 rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                @foreach ($faq as [$question, $answer])
                    <details class="group px-6 py-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
                            {{ $question }}
                            <svg class="h-5 w-5 shrink-0 text-gray-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z" clip-rule="evenodd"/></svg>
                        </summary>
                        <p class="mt-3 text-sm text-gray-600">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        {{-- Call to action --}}
        @guest
            <section class="rounded-3xl bg-gradient-to-br from-indigo-700 to-violet-600 px-6 py-14 text-center text-white sm:px-12">
                <h2 class="text-3xl font-bold tracking-tight">Essayez Konta360 gratuitement pendant {{ $trialDays }} jours</h2>
                <p class="mx-auto mt-4 max-w-xl text-indigo-100">Tous les modules, sans moyen de paiement ni engagement. Vous choisirez votre formule ensuite.</p>
                <a href="{{ route('register') }}" class="mt-8 inline-block rounded-lg bg-white px-6 py-3 text-sm font-semibold text-indigo-700 shadow hover:bg-indigo-50">Créer votre espace</a>
            </section>
        @endguest
    </main>

    <footer class="border-t border-gray-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-6 text-sm text-gray-500 sm:px-6">
            <x-brand />
            <nav class="flex flex-wrap gap-6">
                <a href="{{ route('legal.terms') }}" class="hover:text-gray-900">Conditions générales d’utilisation</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-gray-900">Politique de confidentialité</a>
            </nav>
        </div>
    </footer>

    @if ($paidPlans->isNotEmpty())
        <script>
            (() => {
                // Cheapest paid plan whose limits fit; a null limit is unlimited.
                const plans = @json($finderPlans);
                const users = document.getElementById('finder-users');
                const invoices = document.getElementById('finder-invoices');
                const fits = (limit, value) => limit === null || value <= limit;

                const update = () => {
                    const u = Number(users.value), i = Number(invoices.value);
                    document.getElementById('finder-users-value').textContent = u;
                    document.getElementById('finder-invoices-value').textContent = i;
                    const plan = plans.find(p => fits(p.users, u) && fits(p.invoices, i));
                    document.getElementById('finder-plan').textContent = plan ? plan.name : 'Sur mesure';
                    document.getElementById('finder-price').textContent = plan ? plan.price + ' / mois' : '';
                    document.getElementById('finder-note').textContent = plan
                        ? `Jusqu’à ${plan.users ?? '∞'} utilisateurs et ${plan.invoices ?? '∞'} factures validées par mois. Commencez par l’évaluation gratuite pour vérifier.`
                        : 'Aucune formule ne couvre ce volume : contactez l’équipe Konta360.';
                };

                users.addEventListener('input', update);
                invoices.addEventListener('input', update);
                update();
            })();
        </script>
    @endif
</body>
</html>
