@php
    use App\Modules\Administration\Enums\Permission;
    use App\Modules\Billing\Enums\SubscriptionPaymentMethod;
    use App\Modules\Billing\Enums\SubscriptionPaymentStatus;
    $money = fn ($value, $currency) => number_format((float) $value, 2, ',', ' ').' '.$currency;
@endphp
<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Administration</p><h1 class="text-2xl font-semibold text-gray-900">Abonnement</h1><p class="mt-1 text-sm text-gray-500">Votre formule, son utilisation et vos paiements.</p></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if (session('success'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif

        <section class="grid gap-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:grid-cols-3 sm:p-8">
            <div>
                <p class="text-sm text-gray-500">Formule</p>
                <p class="mt-1 text-xl font-semibold">{{ $company->plan?->name ?? '—' }}</p>
                @if ($company->plan && ! $company->billing_exempt)
                    <p class="text-sm text-gray-500">{{ $money($company->plan->monthly_price, $company->plan->currency) }} / mois</p>
                @endif
            </div>
            <div>
                <p class="text-sm text-gray-500">Statut</p>
                <p class="mt-1 text-xl font-semibold {{ $status->allowsWriting() ? 'text-gray-900' : 'text-red-600' }}">{{ $status->label() }}</p>
                @if ($company->accessEndsOn())
                    <p class="text-sm text-gray-500">Jusqu’au {{ $company->accessEndsOn()->format('d/m/Y') }} inclus</p>
                @endif
            </div>
            <div class="space-y-2 text-sm">
                @foreach (['users' => 'Utilisateurs actifs', 'invoices' => 'Factures validées ce mois-ci'] as $key => $label)
                    @php [$used, $max] = $usage[$key]; @endphp
                    <div>
                        <div class="flex justify-between"><span class="text-gray-500">{{ $label }}</span><span class="font-medium">{{ $used }} / {{ $max ?? '∞' }}</span></div>
                        @if ($max)
                            <div class="mt-1 h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full {{ $used >= $max ? 'bg-red-500' : 'bg-indigo-500' }}" style="width: {{ min(100, round($used / $max * 100)) }}%"></div></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        @if (! $company->billing_exempt)
            @can(Permission::SettingsManage->value)
                <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                    <h2 class="text-lg font-semibold">Payer ou prolonger</h2>
                    <ol class="mt-3 list-inside list-decimal space-y-1 text-sm text-gray-600">
                        <li>Effectuez le paiement par l’un des moyens ci-dessous.</li>
                        <li>Déclarez-le avec sa référence (numéro de transaction mobile money ou de virement).</li>
                        <li>L’équipe Konta360 le vérifie et prolonge votre abonnement. Les jours d’essai ou déjà payés restants ne sont pas perdus.</li>
                    </ol>

                    @if ($instructions)
                        <dl class="mt-4 grid gap-3 rounded-lg bg-gray-50 p-4 text-sm sm:grid-cols-3">
                            @isset($instructions['mobile_money'])<div><dt class="text-gray-500">Mobile Money</dt><dd class="font-medium">{{ $instructions['mobile_money'] }}</dd></div>@endisset
                            @isset($instructions['bank_transfer'])<div><dt class="text-gray-500">Virement bancaire</dt><dd class="font-medium">{{ $instructions['bank_transfer'] }}</dd></div>@endisset
                            @isset($instructions['contact'])<div><dt class="text-gray-500">Contact</dt><dd class="font-medium">{{ $instructions['contact'] }}</dd></div>@endisset
                        </dl>
                    @else
                        <p class="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">Les coordonnées de paiement ne sont pas encore configurées. Contactez l’équipe Konta360.</p>
                    @endif

                    <form method="POST" action="{{ route('subscription.payments.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2" x-data="{ prices: @js($plans->mapWithKeys(fn ($plan) => [$plan->id => [(float) $plan->monthly_price, $plan->currency]])), plan: '{{ old('plan_id', $company->plan_id) }}', months: '{{ old('months', 1) }}' }">
                        @csrf
                        <div>
                            <x-input-label for="plan_id" value="Formule *" />
                            <select id="plan_id" name="plan_id" x-model="plan" class="mt-1 block w-full rounded-md border-gray-300" required>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} — {{ $money($plan->monthly_price, $plan->currency) }} / mois</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('plan_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="months" value="Durée *" />
                            <select id="months" name="months" x-model="months" class="mt-1 block w-full rounded-md border-gray-300" required>
                                @foreach ($durations as $duration)
                                    <option value="{{ $duration }}">{{ $duration }} mois</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="method" value="Moyen de paiement *" />
                            <select id="method" name="method" class="mt-1 block w-full rounded-md border-gray-300" required>
                                @foreach (SubscriptionPaymentMethod::cases() as $method)
                                    <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="reference" value="Référence de la transaction *" />
                            <x-text-input id="reference" name="reference" class="mt-1 block w-full" :value="old('reference')" required />
                            <x-input-error :messages="$errors->get('reference')" class="mt-2" />
                        </div>
                        <div class="flex items-center justify-between gap-4 sm:col-span-2">
                            <p class="text-sm text-gray-600">Montant à payer : <span class="font-semibold text-gray-900" x-text="prices[plan] ? (prices[plan][0] * months).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) + ' ' + prices[plan][1] : '—'"></span></p>
                            <x-primary-button>Déclarer le paiement</x-primary-button>
                        </div>
                    </form>
                </section>
            @endcan
        @endif

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <h2 class="text-lg font-semibold">Historique des paiements</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
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
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-amber-100 text-amber-800' => $payment->status === SubscriptionPaymentStatus::Pending,
                                        'bg-green-100 text-green-700' => $payment->status === SubscriptionPaymentStatus::Confirmed,
                                        'bg-red-100 text-red-700' => $payment->status === SubscriptionPaymentStatus::Rejected,
                                    ])>{{ $payment->status->label() }}</span>
                                    @if ($payment->period_ends_on)<p class="mt-1 text-xs text-gray-500">Du {{ $payment->period_starts_on->format('d/m/Y') }} au {{ $payment->period_ends_on->format('d/m/Y') }}</p>@endif
                                    @if ($payment->rejection_reason)<p class="mt-1 text-xs text-red-600">{{ $payment->rejection_reason }}</p>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-gray-500">Aucun paiement.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div></div>
</x-app-layout>
