@extends('layouts.platform')

@section('title', $company->name)

@php
    use App\Modules\Billing\Enums\SubscriptionPaymentStatus;
    use App\Modules\Billing\Enums\SubscriptionStatus;
    $money = fn ($value, $currency) => number_format((float) $value, 2, ',', ' ').' '.$currency;
    $badge = match ($status) {
        SubscriptionStatus::Active => 'bg-emerald-100 text-emerald-800',
        SubscriptionStatus::Trial => 'bg-sky-100 text-sky-800',
        SubscriptionStatus::Exempt => 'bg-violet-100 text-violet-800',
        SubscriptionStatus::Expired => 'bg-red-100 text-red-700',
    };
    $card = 'rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200';
    $button = 'rounded-md bg-gray-800 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700';
@endphp

@section('content')
    <a href="{{ route('platform.companies.index') }}" class="text-sm text-gray-500 hover:text-gray-900">← Abonnés</a>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $company->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                n° {{ $company->id }} · inscrite le {{ $company->created_at?->format('d/m/Y') }}
                @if ($company->legal_form) · {{ $company->legal_form }}@endif
                @if ($company->tax_identifier) · NIF {{ $company->tax_identifier }}@endif
                · devise {{ $company->default_currency }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @unless ($company->isClosed())
                <form method="POST" action="{{ route('platform.companies.exempt', $company) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ $company->billing_exempt ? 'Retirer l’accès offert' : 'Offrir l’accès' }}</button>
                </form>
                @if ($company->isSuspended())
                    <form method="POST" action="{{ route('platform.companies.reactivate', $company) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Rétablir l’accès</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('platform.companies.suspend', $company) }}" onsubmit="return confirm('Suspendre l’accès de cette société ? Ses utilisateurs seront déconnectés.')">
                        @csrf @method('PATCH')
                        <button type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-500">Suspendre</button>
                    </form>
                @endif
                @if ($company->isClosureRequested())
                    <form method="POST" action="{{ route('platform.companies.close', $company) }}" onsubmit="return confirm('Clôturer définitivement cette société ? Ses données seront conservées pendant la durée légale.')">
                        @csrf @method('PATCH')
                        <button type="submit" class="rounded-md bg-red-800 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Clôturer</button>
                    </form>
                @endif
            @endunless
        </div>
    </div>

    @if ($company->isClosed())
        <p class="mt-4 rounded-lg bg-gray-200 px-4 py-3 text-sm text-gray-700">Clôturée le {{ $company->closed_at->format('d/m/Y') }}. Données conservées jusqu’au {{ $company->closed_at->copy()->addYears(config('konta360.retention_years'))->format('d/m/Y') }}.</p>
    @elseif ($company->isSuspended())
        <p class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">Accès suspendu depuis le {{ $company->suspended_at->format('d/m/Y') }} : ses utilisateurs ne peuvent plus se connecter.</p>
    @endif
    @if ($company->isClosureRequested())
        <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">Clôture demandée le {{ $company->closure_requested_at->format('d/m/Y') }}@if ($company->closureRequester) par {{ $company->closureRequester->name }}@endif.</p>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Subscription --}}
            <section class="{{ $card }}">
                <h2 class="font-semibold">Abonnement</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-gray-500">Formule</p>
                        <p class="mt-1 text-lg font-semibold">{{ $company->plan?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Statut</p>
                        <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-sm font-medium {{ $badge }}">{{ $status->label() }}</span>
                        @if ($company->accessEndsOn())<p class="mt-1 text-xs text-gray-500">jusqu’au {{ $company->accessEndsOn()->format('d/m/Y') }}</p>@endif
                    </div>
                    <div class="space-y-1 text-xs text-gray-600">
                        <p>Évaluation : {{ $company->trial_ends_at?->format('d/m/Y') ?? '—' }}</p>
                        <p>Payé jusqu’au : {{ $company->subscription_ends_at?->format('d/m/Y') ?? '—' }}</p>
                        <p>CGU : {{ $company->terms_version ?? 'non acceptées' }}@if ($company->terms_accepted_at) le {{ $company->terms_accepted_at->format('d/m/Y') }}@endif</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2">
                    @foreach (['users' => 'Utilisateurs actifs', 'invoices' => 'Factures validées ce mois-ci'] as $key => $label)
                        @php [$used, $max] = $usage[$key]; @endphp
                        <div class="text-sm">
                            <div class="flex justify-between"><span class="text-gray-500">{{ $label }}</span><span class="font-medium">{{ $used }} / {{ $max ?? '∞' }}</span></div>
                            @if ($max)
                                <div class="mt-1 h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full {{ $used >= $max ? 'bg-red-500' : 'bg-indigo-500' }}" style="width: {{ min(100, round($used / $max * 100)) }}%"></div></div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @unless ($company->isClosed())
                    <div class="mt-5 grid gap-4 border-t border-gray-100 pt-5 md:grid-cols-2">
                        <form method="POST" action="{{ route('platform.companies.plan', $company) }}" class="space-y-2">
                            @csrf @method('PATCH')
                            <label for="plan_id" class="text-sm font-medium">Changer de formule</label>
                            <div class="flex gap-2">
                                <select id="plan_id" name="plan_id" class="w-full rounded-md border-gray-300 text-sm">
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->id }}" @selected($plan->id === $company->plan_id)>{{ $plan->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="{{ $button }}">Appliquer</button>
                            </div>
                            <p class="text-xs text-gray-500">Sans paiement ni changement de dates.</p>
                        </form>

                        <form method="POST" action="{{ route('platform.companies.trial', $company) }}" class="space-y-2">
                            @csrf @method('PATCH')
                            <label for="days" class="text-sm font-medium">Prolonger l’évaluation</label>
                            <div class="flex gap-2">
                                <input id="days" name="days" type="number" min="1" max="365" value="15" class="w-24 rounded-md border-gray-300 text-sm">
                                <span class="self-center text-sm text-gray-500">jours</span>
                                <button type="submit" class="{{ $button }}">Prolonger</button>
                            </div>
                            <x-input-error :messages="$errors->get('days')" />
                        </form>

                        <form method="POST" action="{{ route('platform.companies.payments.store', $company) }}" class="space-y-2 md:col-span-2">
                            @csrf
                            <p class="text-sm font-medium">Enregistrer un paiement reçu</p>
                            <div class="grid gap-2 sm:grid-cols-5">
                                <select name="plan_id" class="rounded-md border-gray-300 text-sm sm:col-span-1" aria-label="Formule">
                                    @foreach ($purchasablePlans as $plan)
                                        <option value="{{ $plan->id }}" @selected($plan->id === $company->plan_id)>{{ $plan->name }}</option>
                                    @endforeach
                                </select>
                                <select name="months" class="rounded-md border-gray-300 text-sm" aria-label="Durée">
                                    @foreach ([1, 3, 6, 12] as $months)
                                        <option value="{{ $months }}">{{ $months }} mois</option>
                                    @endforeach
                                </select>
                                <select name="method" class="rounded-md border-gray-300 text-sm" aria-label="Moyen">
                                    @foreach ($methods as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                                <input name="reference" required placeholder="Référence" class="rounded-md border-gray-300 text-sm">
                                <button type="submit" class="{{ $button }}" onclick="return confirm('Enregistrer ce paiement comme reçu ? L’abonnement sera prolongé immédiatement.')">Enregistrer</button>
                            </div>
                            <p class="text-xs text-gray-500">Pour un paiement constaté directement (espèces, relevé) : il est confirmé tout de suite et prolonge l’abonnement.</p>
                            <x-input-error :messages="$errors->get('reference')" />
                        </form>
                    </div>
                @endunless
            </section>

            {{-- Users --}}
            <section class="{{ $card }}">
                <h2 class="font-semibold">Utilisateurs</h2>
                <table class="mt-3 min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="py-2 pr-4">Nom</th><th class="py-2 pr-4">Rôle</th><th class="py-2 pr-4">Dernière connexion</th><th class="py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($company->users->sortBy('name') as $user)
                            <tr class="{{ $user->is_active ? '' : 'text-gray-400' }}">
                                <td class="py-2 pr-4">
                                    <p class="font-medium">{{ $user->name }}</p>
                                    <p class="text-xs">{{ $user->email }}@unless ($user->hasVerifiedEmail()) · <span class="text-amber-600">adresse non confirmée</span>@endunless</p>
                                </td>
                                <td class="py-2 pr-4">{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                                <td class="py-2 pr-4">{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('platform.companies.users.toggle', [$company, $user]) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-xs font-medium {{ $user->is_active ? 'text-red-600 hover:text-red-500' : 'text-indigo-600 hover:text-indigo-500' }}">{{ $user->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            {{-- Payments --}}
            <section class="{{ $card }}">
                <h2 class="font-semibold">Paiements</h2>
                <table class="mt-3 min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Formule</th><th class="py-2 pr-4 text-right">Montant</th><th class="py-2 pr-4">Référence</th><th class="py-2">Statut</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="py-2 pr-4">{{ $payment->created_at->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">{{ $payment->plan->name }} · {{ $payment->months }} mois</td>
                                <td class="py-2 pr-4 text-right">{{ $money($payment->amount, $payment->currency) }}</td>
                                <td class="py-2 pr-4">{{ $payment->method->label() }} · <span class="font-mono text-xs">{{ $payment->reference }}</span></td>
                                <td class="py-2">
                                    {{ $payment->status->label() }}
                                    @if ($payment->period_ends_on)<p class="text-xs text-gray-500">{{ $payment->period_starts_on->format('d/m/Y') }} → {{ $payment->period_ends_on->format('d/m/Y') }}</p>@endif
                                    @if ($payment->status === SubscriptionPaymentStatus::Pending)<a href="{{ route('platform.payments.index') }}" class="text-xs text-indigo-600">À vérifier</a>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-gray-500">Aucun paiement.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <div class="space-y-6">
            {{-- Contact --}}
            <section class="{{ $card }} text-sm">
                <h2 class="font-semibold">Coordonnées</h2>
                <dl class="mt-3 space-y-2 text-gray-600">
                    @foreach (['email' => 'E-mail', 'phone' => 'Téléphone', 'address' => 'Adresse', 'representative_name' => 'Représentant', 'trade_register' => 'RCCM'] as $field => $label)
                        <div><dt class="text-xs text-gray-400">{{ $label }}</dt><dd>{{ $company->{$field} ?: '—' }}</dd></div>
                    @endforeach
                </dl>
            </section>

            {{-- Internal notes --}}
            <section class="{{ $card }}">
                <h2 class="font-semibold">Notes internes</h2>
                <p class="mt-1 text-xs text-gray-500">Visibles uniquement par l’équipe Konta360.</p>
                <form method="POST" action="{{ route('platform.companies.notes', $company) }}" class="mt-3 space-y-2">
                    @csrf @method('PUT')
                    <textarea name="platform_notes" rows="5" class="w-full rounded-md border-gray-300 text-sm" placeholder="Échanges, accords commerciaux, relances…">{{ old('platform_notes', $company->platform_notes) }}</textarea>
                    <button type="submit" class="{{ $button }}">Enregistrer</button>
                </form>
            </section>

            {{-- Operator actions --}}
            <section class="{{ $card }}">
                <h2 class="font-semibold">Historique des actions</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    @forelse ($events as $event)
                        <li>
                            <p>{{ $event->description }}</p>
                            <p class="text-xs text-gray-500">{{ $event->actor?->name ?? 'Système' }} · {{ $event->created_at->format('d/m/Y H:i') }}</p>
                        </li>
                    @empty
                        <li class="text-gray-500">Aucune action.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endsection
