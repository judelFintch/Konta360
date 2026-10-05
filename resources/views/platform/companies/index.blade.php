<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sociétés — Plateforme Konta360</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <p class="text-lg font-bold">Konta360 <span class="font-normal text-gray-500">— Plateforme</span></p>
            <form method="POST" action="{{ route('platform.logout') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-gray-600 hover:text-gray-900">Se déconnecter</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">Sociétés inscrites</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $companies->total() }} société(s). Les données comptables des sociétés ne sont pas accessibles depuis cet espace.</p>
            </div>
            <form method="GET" class="flex gap-2">
                <input type="search" name="search" value="{{ $search }}" placeholder="Rechercher une société" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Filtrer</button>
            </form>
        </div>

        @if (session('success'))
            <div class="mt-6 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Société</th>
                        <th class="px-4 py-3">Administrateur(s)</th>
                        <th class="px-4 py-3 text-right">Utilisateurs</th>
                        <th class="px-4 py-3">Inscrite le</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($companies as $company)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $company->name }}</p>
                                <p class="text-xs text-gray-500">{{ $company->default_currency }}@if ($company->tax_identifier) · NIF {{ $company->tax_identifier }}@endif</p>
                            </td>
                            <td class="px-4 py-3">
                                @foreach ($company->users as $admin)
                                    <p>{{ $admin->name }} <span class="text-gray-500">— {{ $admin->email }}</span></p>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-right">{{ $company->users_count }}</td>
                            <td class="px-4 py-3">{{ $company->created_at?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                @if ($company->isSuspended())
                                    <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">Suspendue le {{ $company->suspended_at->format('d/m/Y') }}</span>
                                @else
                                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Active</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($company->isSuspended())
                                    <form method="POST" action="{{ route('platform.companies.reactivate', $company) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="font-medium text-indigo-600 hover:text-indigo-500">Rétablir</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('platform.companies.suspend', $company) }}" onsubmit="return confirm('Suspendre l’accès de cette société ? Ses utilisateurs seront déconnectés.')">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="font-medium text-red-600 hover:text-red-500">Suspendre</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucune société.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $companies->links() }}</div>
    </main>
</body>
</html>
