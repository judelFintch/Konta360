<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Créer votre espace — Konta360</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        {{-- On desktop the page is exactly one screen high and never scrolls. --}}
        <div class="flex min-h-dvh bg-gray-50 lg:h-dvh lg:overflow-hidden">
            {{-- What the company gets: hidden on small screens, where the form comes first. --}}
            <aside class="relative hidden w-[38%] max-w-xl flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-700 via-indigo-600 to-violet-600 p-12 text-white short:p-8 lg:flex">
                <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-white/10"></div>
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-white/5"></div>

                <a href="{{ url('/') }}" class="relative"><x-brand inverted /></a>

                <div class="relative">
                    <h1 class="text-3xl font-bold leading-tight short:text-2xl">La facturation et la comptabilité de votre société, au même endroit.</h1>
                    <ul class="mt-10 space-y-6 short:mt-6 short:space-y-4">
                        @foreach ([
                            ['Un mois d’évaluation gratuit', 'Sans moyen de paiement ni engagement. Vous choisissez ensuite votre formule.'],
                            ['Tous les modules inclus', 'Devis, factures, règlements, comptabilité, trésorerie, immobilisations et états financiers.'],
                            ['Votre équipe, vos accès', 'Invitez vos collaborateurs avec un rôle adapté : comptable, direction ou commercial.'],
                            ['Vos données restent les vôtres', 'Isolées de celles des autres sociétés et exportables à tout moment.'],
                        ] as [$title, $text])
                            <li class="flex gap-4">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/20">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                </span>
                                <div>
                                    <p class="font-semibold">{{ $title }}</p>
                                    <p class="mt-1 text-sm text-indigo-100">{{ $text }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="relative text-sm text-indigo-100">
                    <a href="{{ route('pricing') }}" class="font-semibold text-white underline decoration-white/40 underline-offset-4 hover:decoration-white">Comparer les formules et les tarifs</a>
                </p>
            </aside>

            <main class="flex flex-1 items-start justify-center px-4 py-10 sm:px-8 lg:items-center lg:overflow-y-auto lg:py-6">
                <div class="w-full max-w-xl lg:max-w-3xl">
                    <a href="{{ url('/') }}" class="mb-8 inline-block lg:hidden"><x-brand /></a>
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
