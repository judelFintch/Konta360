@extends('layouts.platform')

@section('title', 'Tableau de bord')

@php
    use App\Modules\Billing\Enums\SubscriptionStatus;
    $money = fn ($amounts) => $amounts->isEmpty()
        ? '0'
        : $amounts->map(fn ($total, $currency) => number_format((float) $total, 2, ',', ' ').' '.$currency)->implode(' + ');
    $statusCards = [
        [SubscriptionStatus::Active, 'Abonnés payants', 'bg-emerald-500'],
        [SubscriptionStatus::Trial, 'En évaluation', 'bg-sky-500'],
        [SubscriptionStatus::Exempt, 'Accès offert', 'bg-violet-500'],
        [SubscriptionStatus::Expired, 'Expirés (lecture seule)', 'bg-red-500'],
    ];
@endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Tableau de bord</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $total }} société(s) ouverte(s) · {{ $signups }} inscription(s) ces 30 derniers jours</p>
        </div>
        <a href="{{ route('platform.companies.export') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Exporter les abonnés (CSV)</a>
    </div>

    {{-- Subscribers by status --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($statusCards as [$status, $label, $dot])
            <a href="{{ route('platform.companies.index', ['status' => $status->value]) }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 hover:ring-indigo-300">
                <p class="flex items-center gap-2 text-sm text-gray-500"><span class="h-2 w-2 rounded-full {{ $dot }}"></span>{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold">{{ $byStatus[$status->value] }}</p>
            </a>
        @endforeach
    </div>

    {{-- Money and things to handle --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 lg:col-span-2">
            <p class="text-sm text-gray-500">Revenu mensuel récurrent</p>
            <p class="mt-2 text-2xl font-bold">{{ $money($recurring) }}</p>
            <p class="mt-1 text-xs text-gray-500">Prix mensuel des formules des abonnés payants.</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <p class="text-sm text-gray-500">Encaissé ce mois-ci</p>
            <p class="mt-2 text-2xl font-bold">{{ $money($collected) }}</p>
            <p class="mt-1 text-xs text-gray-500">Paiements confirmés en {{ now()->translatedFormat('F Y') }}.</p>
        </div>
        <div class="space-y-2 rounded-xl bg-white p-5 text-sm shadow-sm ring-1 ring-gray-200">
            <a href="{{ route('platform.payments.index') }}" class="flex justify-between hover:text-indigo-600"><span>Paiements à vérifier</span><span @class(['font-semibold', 'text-amber-600' => $pendingPayments > 0])>{{ $pendingPayments }}</span></a>
            <a href="{{ route('platform.companies.index', ['access' => 'closure']) }}" class="flex justify-between hover:text-indigo-600"><span>Clôtures demandées</span><span @class(['font-semibold', 'text-amber-600' => $closureRequests > 0])>{{ $closureRequests }}</span></a>
            <a href="{{ route('platform.companies.index', ['access' => 'suspended']) }}" class="flex justify-between hover:text-indigo-600"><span>Sociétés suspendues</span><span class="font-semibold">{{ $suspended }}</span></a>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- Access ending within a week --}}
        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <h2 class="font-semibold">Accès se terminant dans les 7 jours</h2>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($endingSoon as $company)
                    <li class="flex items-center justify-between py-2">
                        <a href="{{ route('platform.companies.show', $company) }}" class="font-medium hover:text-indigo-600">{{ $company->name }}</a>
                        <span class="text-gray-500">{{ $company->subscriptionStatus()->label() }} · {{ $company->accessEndsOn()->format('d/m/Y') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">Aucune échéance cette semaine.</li>
                @endforelse
            </ul>
        </section>

        {{-- Latest sign-ups --}}
        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <h2 class="font-semibold">Dernières inscriptions</h2>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($latestSignups as $company)
                    <li class="flex items-center justify-between py-2">
                        <a href="{{ route('platform.companies.show', $company) }}" class="font-medium hover:text-indigo-600">{{ $company->name }}</a>
                        <span class="text-gray-500">{{ $company->plan?->name }} · {{ $company->created_at?->format('d/m/Y') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">Aucune société.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- Operator actions --}}
    <section class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <h2 class="font-semibold">Dernières actions de la plateforme</h2>
        <ul class="mt-3 divide-y divide-gray-100 text-sm">
            @forelse ($events as $event)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>
                        @if ($event->company)<a href="{{ route('platform.companies.show', $event->company) }}" class="font-medium hover:text-indigo-600">{{ $event->company->name }}</a> — @endif
                        {{ $event->description }}
                    </span>
                    <span class="text-gray-500">{{ $event->actor?->name ?? 'Système' }} · {{ $event->created_at->format('d/m/Y H:i') }}</span>
                </li>
            @empty
                <li class="py-2 text-gray-500">Aucune action enregistrée.</li>
            @endforelse
        </ul>
    </section>
@endsection
