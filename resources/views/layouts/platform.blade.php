<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — Plateforme Konta360</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
    @php
        $pendingPayments = \App\Models\SubscriptionPayment::query()
            ->withoutGlobalScope(\App\Modules\Companies\Scopes\CompanyScope::class)
            ->where('status', \App\Modules\Billing\Enums\SubscriptionPaymentStatus::Pending)
            ->count();
        $links = [
            'platform.dashboard' => 'Tableau de bord',
            'platform.companies.index' => 'Abonnés',
            'platform.payments.index' => 'Paiements',
            'platform.plans.index' => 'Formules',
        ];
    @endphp
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-6">
                <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-2"><x-brand /><span class="rounded-full bg-gray-900 px-2 py-0.5 text-xs font-semibold text-white">Super admin</span></a>
                <nav class="flex gap-1">
                    @foreach ($links as $route => $label)
                        <a href="{{ route($route) }}" class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs($route === 'platform.dashboard' ? $route : str_replace('.index', '.*', $route)) ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                            {{ $label }}
                            @if ($route === 'platform.payments.index' && $pendingPayments > 0)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $pendingPayments }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </div>
            <form method="POST" action="{{ route('platform.logout') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-gray-600 hover:text-gray-900">Se déconnecter</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="mb-6 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
