@php use App\Modules\Accounting\Enums\PeriodStatus; @endphp

<x-app-layout>
    <x-slot name="header">
        <div><p class="text-sm font-medium text-indigo-600">Administration comptable</p><h1 class="text-2xl font-semibold text-gray-900">Périodes et clôtures</h1><p class="mt-1 text-sm text-gray-500">Définissez les exercices comptables et clôturez-les pour verrouiller définitivement leurs écritures.</p></div>
    </x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('accounting.reports._navigation')
        @if (session('success'))<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif

        <div class="grid gap-6 lg:grid-cols-[360px_1fr]">
            <form method="POST" action="{{ route('accounting.periods.store') }}" class="h-fit rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                @csrf
                <h2 class="font-semibold text-gray-900">Nouvel exercice</h2>
                <p class="mt-1 text-sm text-gray-500">Les périodes ne peuvent pas se chevaucher.</p>
                <div class="mt-5">
                    <x-input-label for="name" value="Libellé *" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', 'Exercice '.now()->year)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div class="mt-5">
                    <x-input-label for="starts_on" value="Date de début *" />
                    <x-text-input id="starts_on" name="starts_on" type="date" class="mt-1 block w-full" :value="old('starts_on', now()->startOfYear()->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('starts_on')" class="mt-2" />
                </div>
                <div class="mt-5">
                    <x-input-label for="ends_on" value="Date de fin *" />
                    <x-text-input id="ends_on" name="ends_on" type="date" class="mt-1 block w-full" :value="old('ends_on', now()->endOfYear()->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('ends_on')" class="mt-2" />
                </div>
                <x-primary-button class="mt-6 w-full justify-center">Créer la période</x-primary-button>
            </form>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-200 px-6 py-5"><h2 class="font-semibold text-gray-900">Historique des périodes</h2></div>
                <div class="divide-y divide-gray-100">
                    @forelse ($periods as $period)
                        <div class="p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex items-center gap-3">
                                        <h3 class="font-semibold text-gray-900">{{ $period->name }}</h3>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $period->status === PeriodStatus::Open ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">{{ $period->status->label() }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-600">Du {{ $period->starts_on->format('d/m/Y') }} au {{ $period->ends_on->format('d/m/Y') }}</p>
                                    @if ($period->closed_at)<p class="mt-1 text-xs text-gray-500">Clôturée le {{ $period->closed_at->format('d/m/Y à H:i') }} par {{ $period->closer->name }}</p>@endif
                                </div>
                                @if ($period->status === PeriodStatus::Open)
                                    <form method="POST" action="{{ route('accounting.periods.close', $period) }}" onsubmit="return confirm('Cette clôture bloquera définitivement les nouvelles écritures sur cette période. Continuer ?')">
                                        @csrf @method('PATCH')
                                        <button class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-500 {{ $period->ends_on->isFuture() ? 'cursor-not-allowed opacity-50' : '' }}" @disabled($period->ends_on->isFuture())>Clôturer</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-14 text-center text-gray-500">Aucune période comptable définie.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>Important :</strong> une clôture est irréversible depuis l’interface. Vérifiez la balance et le grand livre avant de confirmer.</div>
    </div></div>
</x-app-layout>
