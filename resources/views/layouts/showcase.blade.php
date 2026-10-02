<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Konta360') }} — Comptabilité SYSCOHADA</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-white">
        <div class="min-h-screen lg:grid lg:grid-cols-2">
            <!-- Présentation du logiciel -->
            <aside class="relative overflow-hidden bg-gradient-to-br from-indigo-700 via-indigo-800 to-slate-900 text-white">
                <div aria-hidden="true" class="pointer-events-none absolute -top-24 -right-24 h-80 w-80 rounded-full bg-indigo-400/20 blur-3xl"></div>
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-emerald-400/10 blur-3xl"></div>

                <div class="relative flex h-full flex-col px-6 py-8 sm:px-10 lg:px-14 lg:py-10">
                    <!-- Marque -->
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-lg font-extrabold text-indigo-700 shadow-lg">K</span>
                        <span class="text-xl font-bold tracking-tight">Konta<span class="text-emerald-300">360</span></span>
                    </div>

                    <!-- Accroche -->
                    <div class="mt-8 lg:mt-12">
                        <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-indigo-100 ring-1 ring-white/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                            Conforme au référentiel SYSCOHADA révisé
                        </p>
                        <h1 class="mt-4 text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl xl:text-5xl">
                            Votre comptabilité,<br>
                            <span class="text-emerald-300">de la facture au bilan.</span>
                        </h1>
                        <p class="mt-4 max-w-md text-base text-indigo-100 sm:text-lg">
                            Konta360 réunit facturation, comptabilité générale, trésorerie et états financiers
                            dans un seul outil, pensé pour les entreprises de l'espace OHADA.
                        </p>
                    </div>

                    <!-- Points forts -->
                    <ul class="mt-8 hidden gap-4 sm:grid sm:grid-cols-2 lg:mt-10">
                        @foreach ([
                            ['Facturation', 'Devis, factures, avoirs et règlements multi-modes.', 'M9 12h6m-6 4h6M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z'],
                            ['Comptabilité générale', 'Écritures automatiques et saisie manuelle équilibrée.', 'M4 19h16M4 15l4-4 4 4 8-8'],
                            ['Trésorerie', 'Suivi des encaissements et rapprochement bancaire.', 'M3 10h18M5 6h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Zm2 8h3'],
                            ['États financiers', 'Balance, grand livre, bilan et exports en un clic.', 'M9 17V9m4 8V5m4 12v-6M5 21h14'],
                        ] as [$title, $text, $icon])
                            <li class="flex gap-3 rounded-xl bg-white/5 p-4 ring-1 ring-white/10 backdrop-blur-sm">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-400/15 text-emerald-300">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                                </span>
                                <span>
                                    <span class="block text-sm font-semibold">{{ $title }}</span>
                                    <span class="mt-0.5 block text-sm text-indigo-200">{{ $text }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <!-- Visuel : aperçu du tableau de bord -->
                    <div aria-hidden="true" class="mt-8 hidden rounded-2xl bg-white/95 p-5 text-gray-900 shadow-2xl ring-1 ring-black/5 lg:block xl:max-w-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-700">Tableau de bord</span>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Exercice en cours</span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3">
                            <div class="rounded-lg bg-indigo-50 p-3">
                                <span class="block text-[11px] uppercase tracking-wide text-indigo-500">Chiffre d'affaires</span>
                                <span class="mt-1 block h-3 w-16 rounded bg-indigo-300"></span>
                            </div>
                            <div class="rounded-lg bg-emerald-50 p-3">
                                <span class="block text-[11px] uppercase tracking-wide text-emerald-600">Encaissé</span>
                                <span class="mt-1 block h-3 w-12 rounded bg-emerald-300"></span>
                            </div>
                            <div class="rounded-lg bg-amber-50 p-3">
                                <span class="block text-[11px] uppercase tracking-wide text-amber-600">À recouvrer</span>
                                <span class="mt-1 block h-3 w-10 rounded bg-amber-300"></span>
                            </div>
                        </div>
                        <div class="mt-4 flex h-14 items-end gap-2">
                            @foreach ([40, 55, 35, 70, 60, 85, 75, 95] as $height)
                                <span class="flex-1 rounded-t bg-gradient-to-t from-indigo-500 to-indigo-300" style="height: {{ $height }}%"></span>
                            @endforeach
                        </div>
                    </div>

                    <p class="mt-auto hidden pt-8 text-xs text-indigo-300 lg:block">
                        © {{ date('Y') }} Konta360 — Logiciel de gestion comptable et de facturation.
                    </p>
                </div>
            </aside>

            <!-- Formulaire -->
            <main class="flex items-center justify-center px-6 py-10 sm:px-10 lg:py-12">
                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
