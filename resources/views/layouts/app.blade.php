<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Models\Company::current()->name }} — Konta360</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            <livewire:layout.navigation />
            @if (auth()->user()?->is_platform_admin)
                <div class="bg-amber-100 px-4 py-3 text-amber-950">
                    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 text-sm">
                        <p><strong>Mode assistance super-admin</strong> — {{ \App\Models\Company::current()->name }}. Vos actions sont journalisées.</p>
                        <form method="POST" action="{{ route('platform.company-access.exit') }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-amber-800 px-3 py-1.5 font-semibold hover:bg-amber-200">Quitter l’assistance</button>
                        </form>
                    </div>
                </div>
            @endif
            <x-subscription-banner />

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
