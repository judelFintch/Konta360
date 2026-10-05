@extends('layouts.platform')

@section('title', 'Sociétés')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Sociétés inscrites</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $companies->total() }} société(s). Les données comptables des sociétés ne sont pas accessibles depuis cet espace.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="closure" value="1" @checked(request()->boolean('closure')) class="rounded border-gray-300 text-indigo-600">
                Clôtures demandées
            </label>
            <input type="search" name="search" value="{{ $search }}" placeholder="Rechercher une société" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Filtrer</button>
        </form>
    </div>

    <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Société</th>
                    <th class="px-4 py-3">Administrateur(s)</th>
                    <th class="px-4 py-3 text-right">Utilisateurs</th>
                    <th class="px-4 py-3">Abonnement</th>
                    <th class="px-4 py-3">Accès</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($companies as $company)
                    @php $subscription = $company->subscriptionStatus(); @endphp
                    <tr class="align-top">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $company->name }}</p>
                            <p class="text-xs text-gray-500">n° {{ $company->id }} · inscrite le {{ $company->created_at?->format('d/m/Y') }}@if ($company->tax_identifier) · NIF {{ $company->tax_identifier }}@endif</p>
                        </td>
                        <td class="px-4 py-3">
                            @foreach ($company->users as $admin)
                                <p>{{ $admin->name }} <span class="text-gray-500">— {{ $admin->email }}</span></p>
                            @endforeach
                        </td>
                        <td class="px-4 py-3 text-right">{{ $company->users_count }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $company->plan?->name ?? '—' }}</p>
                            <p class="text-xs {{ $subscription->allowsWriting() ? 'text-gray-500' : 'font-medium text-red-600' }}">
                                {{ $subscription->label() }}@if ($company->accessEndsOn()) jusqu’au {{ $company->accessEndsOn()->format('d/m/Y') }}@endif
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            @if ($company->isClosed())
                                <span class="rounded-full bg-gray-200 px-2 py-1 text-xs font-medium text-gray-700">Clôturée le {{ $company->closed_at->format('d/m/Y') }}</span>
                            @elseif ($company->isSuspended())
                                <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">Suspendue le {{ $company->suspended_at->format('d/m/Y') }}</span>
                            @else
                                <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Active</span>
                            @endif
                            @if ($company->isClosureRequested())
                                <p class="mt-2 text-xs font-medium text-amber-700">Clôture demandée le {{ $company->closure_requested_at->format('d/m/Y') }}</p>
                            @endif
                        </td>
                        <td class="space-y-2 px-4 py-3 text-right">
                            @unless ($company->isClosed())
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
                                <form method="POST" action="{{ route('platform.companies.exempt', $company) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="font-medium text-gray-600 hover:text-gray-900">{{ $company->billing_exempt ? 'Retirer l’accès offert' : 'Offrir l’accès' }}</button>
                                </form>
                                @if ($company->isClosureRequested())
                                    <form method="POST" action="{{ route('platform.companies.close', $company) }}" onsubmit="return confirm('Clôturer définitivement cette société ? Ses utilisateurs perdront l’accès ; les données seront conservées pendant la durée légale.')">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="font-medium text-red-700 hover:text-red-600">Clôturer</button>
                                    </form>
                                @endif
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucune société.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $companies->links() }}</div>
@endsection
