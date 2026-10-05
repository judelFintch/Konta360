@extends('layouts.platform')

@section('title', 'Abonnés')

@php
    use App\Modules\Billing\Enums\SubscriptionStatus;
    $badge = fn (SubscriptionStatus $status) => match ($status) {
        SubscriptionStatus::Active => 'bg-emerald-100 text-emerald-800',
        SubscriptionStatus::Trial => 'bg-sky-100 text-sky-800',
        SubscriptionStatus::Exempt => 'bg-violet-100 text-violet-800',
        SubscriptionStatus::Expired => 'bg-red-100 text-red-700',
    };
@endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Abonnés</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $companies->total() }} société(s). Les données comptables des sociétés ne sont pas accessibles depuis cet espace.</p>
        </div>
        <a href="{{ route('platform.companies.export', request()->query()) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Exporter cette liste (CSV)</a>
    </div>

    <form method="GET" class="mt-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Société, NIF ou e-mail" class="rounded-md border-gray-300 text-sm lg:col-span-2">
        <select name="status" class="rounded-md border-gray-300 text-sm">
            <option value="">Tous les statuts</option>
            @foreach (SubscriptionStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected($filters['status'] === $status)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <select name="plan" class="rounded-md border-gray-300 text-sm">
            <option value="">Toutes les formules</option>
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}" @selected($filters['plan'] === $plan->id)>{{ $plan->name }}</option>
            @endforeach
        </select>
        <select name="access" class="rounded-md border-gray-300 text-sm">
            <option value="">Tous les accès</option>
            <option value="suspended" @selected($filters['access'] === 'suspended')>Suspendues</option>
            <option value="closure" @selected($filters['access'] === 'closure')>Clôture demandée</option>
            <option value="closed" @selected($filters['access'] === 'closed')>Clôturées</option>
        </select>
        <div class="flex gap-2">
            <select name="sort" class="w-full rounded-md border-gray-300 text-sm" aria-label="Trier">
                <option value="recent" @selected($filters['sort'] === 'recent')>Plus récentes</option>
                <option value="ends" @selected($filters['sort'] === 'ends')>Échéance proche</option>
                <option value="login" @selected($filters['sort'] === 'login')>Dernière connexion</option>
                <option value="name" @selected($filters['sort'] === 'name')>Nom</option>
            </select>
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Filtrer</button>
        </div>
    </form>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Société</th>
                    <th class="px-4 py-3">Formule et statut</th>
                    <th class="px-4 py-3 text-right">Utilisateurs</th>
                    <th class="px-4 py-3">Dernière connexion</th>
                    <th class="px-4 py-3">Accès</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($companies as $company)
                    @php $subscription = $company->subscriptionStatus(); @endphp
                    <tr class="align-top hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('platform.companies.show', $company) }}" class="font-medium text-indigo-700 hover:text-indigo-500">{{ $company->name }}</a>
                            <p class="text-xs text-gray-500">{{ $company->users->pluck('email')->implode(', ') ?: '—' }}</p>
                            <p class="text-xs text-gray-400">n° {{ $company->id }} · inscrite le {{ $company->created_at?->format('d/m/Y') }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $company->plan?->name ?? '—' }}</p>
                            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $badge($subscription) }}">{{ $subscription->label() }}</span>
                            @if ($company->accessEndsOn())<p class="mt-1 text-xs text-gray-500">jusqu’au {{ $company->accessEndsOn()->format('d/m/Y') }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-right">{{ $company->active_users_count }}<span class="text-gray-400"> / {{ $company->users_count }}</span></td>
                        <td class="px-4 py-3 text-gray-600">{{ $company->users_max_last_login_at ? \Illuminate\Support\Carbon::parse($company->users_max_last_login_at)->diffForHumans() : 'Jamais' }}</td>
                        <td class="px-4 py-3">
                            @if ($company->isClosed())
                                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700">Clôturée</span>
                            @elseif ($company->isSuspended())
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Suspendue</span>
                            @else
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Ouverte</span>
                            @endif
                            @if ($company->isClosureRequested())<p class="mt-1 text-xs font-medium text-amber-700">Clôture demandée</p>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucune société ne correspond à ces critères.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $companies->links() }}</div>
@endsection
