@php
    use App\Modules\Administration\Enums\Permission;

    $money = fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', ' ');
    $user = auth()->user();
    $canFinance = $user->canAny([Permission::InvoicesView->value, Permission::AccountingView->value, Permission::FinancialStatementsView->value]);
    $missingSettings = blank($company->tax_identifier) || blank($company->trade_register) || blank($company->address) || $company->name === config('app.name');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-indigo-600">{{ $company->name }}</p>
                <h1 class="text-2xl font-semibold text-gray-900">Tableau de bord</h1>
            </div>
            <p class="text-sm text-gray-500">
                Bonjour <span class="font-medium text-gray-700">{{ Str::before($user->name, ' ') ?: $user->name }}</span>,
                nous sommes le {{ today()->locale('fr')->translatedFormat('l j F Y') }}.
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Alertes --}}
            @php
                $alerts = array_filter([
                    $missingSettings && $user->can(Permission::SettingsManage->value)
                        ? ['Les informations légales de l’entreprise sont incomplètes : elles manquent sur vos factures.', route('administration.company.edit'), 'Compléter']
                        : null,
                    $draftInvoicesCount > 0 && $user->can(Permission::InvoicesView->value)
                        ? [$draftInvoicesCount.' facture(s) en brouillon attendent d’être validées.', route('invoices.index', ['status' => 'draft']), 'Voir']
                        : null,
                ]);
            @endphp
            @if ($alerts)
                <div class="space-y-2">
                    @foreach ($alerts as [$message, $url, $action])
                        <div class="flex items-center justify-between gap-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <span class="flex items-center gap-2">
                                <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                                {{ $message }}
                            </span>
                            <a href="{{ $url }}" class="shrink-0 font-semibold text-amber-900 underline-offset-2 hover:underline" wire:navigate>{{ $action }} →</a>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($canFinance)
                {{-- Indicateurs du mois --}}
                <section>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        {{ ucfirst(today()->locale('fr')->translatedFormat('F Y')) }}
                    </h2>
                    <div class="mt-3 space-y-4">
                        @foreach ($metrics as $currency => $metric)
                            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                @foreach ([
                                    ['Chiffre d’affaires du mois', $metric['sales'], 'text-gray-900', 'Factures validées, avoirs déduits'],
                                    ['Encaissé ce mois', $metric['collected'], 'text-emerald-600', 'Règlements clients enregistrés'],
                                    ['Dépenses du mois', $metric['expenses'], 'text-amber-700', 'Charges fournisseurs validées'],
                                    ['Reste à encaisser', $metric['outstanding'], 'text-indigo-700', 'Toutes factures confondues'],
                                ] as [$label, $value, $color, $hint])
                                    <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-5">
                                        <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
                                        <p class="mt-2 text-xl font-semibold sm:text-2xl {{ $color }}">
                                            {{ $money($value) }} <span class="text-sm font-medium text-gray-400">{{ $currency }}</span>
                                        </p>
                                        @if ($loop->last && $metric['overdue_count'] > 0)
                                            <p class="mt-1 text-xs font-medium text-red-600">dont {{ $money($metric['overdue_amount']) }} en retard ({{ $metric['overdue_count'] }} facture(s) échue(s))</p>
                                        @else
                                            <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Évolution et trésorerie --}}
                <section class="grid gap-6 lg:grid-cols-[1.6fr_1fr]">
                    <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h2 class="font-semibold text-gray-900">Ventes des six derniers mois</h2>
                        <p class="mt-1 text-sm text-gray-500">Montant TTC des factures validées, avant avoirs.</p>
                        <div class="mt-6 space-y-8">
                            @forelse ($maxMonthly as $currency => $max)
                                <div>
                                    <p class="mb-2 text-xs font-semibold text-gray-500">{{ $currency }}</p>
                                    <div class="flex h-36 items-end gap-2 sm:gap-4">
                                        @foreach ($months as $month)
                                            @php $value = $month['totals'][$currency]; @endphp
                                            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                                                <span class="text-[11px] font-semibold {{ $value > 0 ? 'text-gray-700' : 'text-gray-300' }}">{{ $value > 0 ? $money($value, 0) : '—' }}</span>
                                                <div class="w-full max-w-[48px] rounded-t-md {{ $loop->last ? 'bg-indigo-600' : 'bg-indigo-300' }}" style="height: {{ $value > 0 ? max(4, $value / $max * 100) : 1 }}%"></div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="mt-2 flex gap-2 border-t border-gray-100 pt-2 sm:gap-4">
                                        @foreach ($months as $month)
                                            <span class="flex-1 text-center text-xs {{ $loop->last ? 'font-semibold text-gray-900' : 'text-gray-500' }}">{{ $month['label'] }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <p class="py-10 text-center text-sm text-gray-500">Aucune facture validée sur les six derniers mois.</p>
                            @endforelse
                        </div>
                    </article>

                    <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="font-semibold text-gray-900">Trésorerie</h2>
                                <p class="mt-1 text-sm text-gray-500">Soldes des banques et caisses.</p>
                            </div>
                            @can(Permission::TreasuryManage->value)<a href="{{ route('treasury.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" wire:navigate>Ouvrir →</a>@endcan
                        </div>
                        <div class="mt-4 divide-y divide-gray-100">
                            @forelse ($treasury as $account)
                                <div class="flex items-center justify-between gap-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $account->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $account->type->label() }}</p>
                                    </div>
                                    <p class="shrink-0 font-semibold {{ $account->balance() < 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $money($account->balance()) }} <span class="text-xs text-gray-400">{{ $account->currency }}</span></p>
                                </div>
                            @empty
                                <p class="py-8 text-center text-sm text-gray-500">Aucun compte de trésorerie actif.</p>
                            @endforelse
                        </div>
                    </article>
                </section>
            @endif

            {{-- Listes à traiter --}}
            @canany([Permission::InvoicesView->value, Permission::QuotesView->value])
                <section class="grid gap-6 lg:grid-cols-2">
                    @can(Permission::InvoicesView->value)
                        <article class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                                <div>
                                    <h2 class="font-semibold text-gray-900">À relancer</h2>
                                    <p class="text-sm text-gray-500">Factures échues non soldées, les plus anciennes d’abord.</p>
                                </div>
                                <a href="{{ route('invoices.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" wire:navigate>Tout voir →</a>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                @forelse ($overdueInvoices as $invoice)
                                    <li>
                                        <a href="{{ route('invoices.show', $invoice) }}" class="flex items-center justify-between gap-4 px-6 py-3 hover:bg-gray-50" wire:navigate>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-gray-900">{{ $invoice->party->name }}</p>
                                                <p class="text-xs text-gray-500">{{ $invoice->number }} · échue le {{ $invoice->due_date->format('d/m/Y') }}</p>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <p class="text-sm font-semibold text-gray-900">{{ $money($balances[$invoice->id]) }} {{ $invoice->currency }}</p>
                                                <p class="text-xs font-medium text-red-600">{{ (int) $invoice->due_date->diffInDays(today()) }} j de retard</p>
                                            </div>
                                        </a>
                                    </li>
                                @empty
                                    <li class="px-6 py-10 text-center text-sm text-gray-500">Aucune facture en retard.</li>
                                @endforelse
                            </ul>
                        </article>
                    @endcan

                    @can(Permission::QuotesView->value)
                        <article class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                                <div>
                                    <h2 class="font-semibold text-gray-900">Devis en attente</h2>
                                    <p class="text-sm text-gray-500">Envoyés au client, sans réponse.</p>
                                </div>
                                <a href="{{ route('quotes.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" wire:navigate>Tout voir →</a>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                @forelse ($pendingQuotes as $quote)
                                    <li>
                                        <a href="{{ route('quotes.show', $quote) }}" class="flex items-center justify-between gap-4 px-6 py-3 hover:bg-gray-50" wire:navigate>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-gray-900">{{ $quote->party->name }}</p>
                                                <p class="text-xs text-gray-500">{{ $quote->number }} · émis le {{ $quote->issue_date->format('d/m/Y') }}</p>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <p class="text-sm font-semibold text-gray-900">{{ $money($quote->total) }} {{ $quote->currency }}</p>
                                                @if ($quote->valid_until->lt(today()))
                                                    <p class="text-xs font-medium text-red-600">Expiré le {{ $quote->valid_until->format('d/m/Y') }}</p>
                                                @else
                                                    <p class="text-xs text-gray-500">Valable jusqu’au {{ $quote->valid_until->format('d/m/Y') }}</p>
                                                @endif
                                            </div>
                                        </a>
                                    </li>
                                @empty
                                    <li class="px-6 py-10 text-center text-sm text-gray-500">Aucun devis en attente de réponse.</li>
                                @endforelse
                            </ul>
                        </article>
                    @endcan
                </section>
            @endcanany

            @unless ($canFinance || $user->canAny([Permission::InvoicesView->value, Permission::QuotesView->value]))
                <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-200">
                    <p class="font-semibold text-gray-900">Bienvenue sur l’espace de {{ $company->name }}</p>
                    <p class="mt-1 text-sm text-gray-500">Utilisez le menu ci-dessus pour accéder aux modules autorisés pour votre compte.</p>
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
