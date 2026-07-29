<?php

use App\Livewire\Actions\Logout;
use App\Modules\Administration\Enums\Permission;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden items-center gap-2 sm:ms-8 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        Accueil
                    </x-nav-link>

                    @canany([Permission::QuotesView->value, Permission::InvoicesView->value, Permission::PaymentsRecord->value])
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('quotes.*', 'invoices.*', 'credit-notes.*', 'payments.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                    Ventes
                                    <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                @can(Permission::QuotesView->value)<x-dropdown-link :href="route('quotes.index')" wire:navigate>Devis</x-dropdown-link>@endcan
                                @can(Permission::InvoicesView->value)<x-dropdown-link :href="route('invoices.index')" wire:navigate>Factures</x-dropdown-link>@endcan
                                @can(Permission::InvoicesView->value)<x-dropdown-link :href="route('credit-notes.index')" wire:navigate>Avoirs</x-dropdown-link>@endcan
                                @canany([Permission::PaymentsRecord->value, Permission::PaymentsReverse->value])<x-dropdown-link :href="route('payments.index')" wire:navigate>Règlements</x-dropdown-link>@endcanany
                            </x-slot>
                        </x-dropdown>
                    @endcanany

                    @canany([Permission::AccountingView->value, Permission::FinancialStatementsView->value])
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('accounting.*', 'financial-statements.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                    Comptabilité
                                    <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                @can(Permission::AccountingView->value)<x-dropdown-link :href="route('accounting.entries.index')" wire:navigate>Journal et rapports</x-dropdown-link>@endcan
                                @can(Permission::FinancialStatementsView->value)<x-dropdown-link :href="route('financial-statements.income-statement')" wire:navigate>États financiers</x-dropdown-link>@endcan
                                @can(Permission::AccountingPeriodsClose->value)<x-dropdown-link :href="route('accounting.periods.index')" wire:navigate>Périodes et clôture</x-dropdown-link>@endcan
                            </x-slot>
                        </x-dropdown>
                    @endcanany

                    @canany([Permission::PartiesManage->value, Permission::CatalogManage->value, Permission::FixedAssetsManage->value])
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('parties.*', 'catalog.*', 'fixed-assets.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                    Référentiels
                                    <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                @can(Permission::PartiesManage->value)<x-dropdown-link :href="route('parties.index')" wire:navigate>Clients et fournisseurs</x-dropdown-link>@endcan
                                @can(Permission::CatalogManage->value)<x-dropdown-link :href="route('catalog.index')" wire:navigate>Produits et services</x-dropdown-link>@endcan
                                @can(Permission::FixedAssetsManage->value)<x-dropdown-link :href="route('fixed-assets.index')" wire:navigate>Immobilisations</x-dropdown-link>@endcan
                            </x-slot>
                        </x-dropdown>
                    @endcanany
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                Tableau de bord
            </x-responsive-nav-link>
            @can(Permission::PartiesManage->value)
                <x-responsive-nav-link :href="route('parties.index')" :active="request()->routeIs('parties.*')" wire:navigate>
                    Tiers
                </x-responsive-nav-link>
            @endcan
            @can(Permission::CatalogManage->value)
                <x-responsive-nav-link :href="route('catalog.index')" :active="request()->routeIs('catalog.*')" wire:navigate>
                    Catalogue
                </x-responsive-nav-link>
            @endcan
            @can(Permission::QuotesView->value)
                <x-responsive-nav-link :href="route('quotes.index')" :active="request()->routeIs('quotes.*')" wire:navigate>
                    Devis
                </x-responsive-nav-link>
            @endcan
            @can(Permission::InvoicesView->value)
                <x-responsive-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')" wire:navigate>
                    Factures
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('credit-notes.index')" :active="request()->routeIs('credit-notes.*')" wire:navigate>
                    Avoirs
                </x-responsive-nav-link>
            @endcan
            @canany([Permission::PaymentsRecord->value, Permission::PaymentsReverse->value])
                <x-responsive-nav-link :href="route('payments.index')" :active="request()->routeIs('payments.*')" wire:navigate>
                    Règlements
                </x-responsive-nav-link>
            @endcanany
            @can(Permission::AccountingView->value)
                <x-responsive-nav-link :href="route('accounting.entries.index')" :active="request()->routeIs('accounting.*')" wire:navigate>
                    Comptabilité
                </x-responsive-nav-link>
            @endcan
            @can(Permission::FinancialStatementsView->value)
                <x-responsive-nav-link :href="route('financial-statements.income-statement')" :active="request()->routeIs('financial-statements.*')" wire:navigate>
                    États financiers
                </x-responsive-nav-link>
            @endcan
            @can(Permission::FixedAssetsManage->value)
                <x-responsive-nav-link :href="route('fixed-assets.index')" :active="request()->routeIs('fixed-assets.*')" wire:navigate>
                    Immobilisations
                </x-responsive-nav-link>
            @endcan
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
