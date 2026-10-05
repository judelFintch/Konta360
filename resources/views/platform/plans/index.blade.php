@extends('layouts.platform')

@section('title', 'Formules')

@section('content')
    <h1 class="text-2xl font-bold">Formules</h1>
    <p class="mt-1 text-sm text-gray-500"><a href="{{ route('pricing') }}" target="_blank" class="font-medium text-indigo-600">Voir la page publique des tarifs</a>. Laisser une limite vide la rend illimitée. Une formule désactivée n’est plus proposée, mais les sociétés qui l’ont la conservent.</p>

    <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($plans as $plan)
            <form method="POST" action="{{ route('platform.plans.update', $plan) }}" class="space-y-4 rounded-lg bg-white p-6 shadow">
                @csrf @method('PUT')
                @if ($plan->is_evaluation)
                    <p class="rounded-md bg-emerald-50 p-2 text-xs text-emerald-800">Formule de départ de toute nouvelle société, gratuite et non achetable. Sa durée vient de BILLING_TRIAL_DAYS ; son prix n’est pas utilisé.</p>
                @endif
                <div class="flex items-start justify-between">
                    <p class="font-mono text-xs text-gray-500">{{ $plan->code }}</p>
                    <p class="text-xs text-gray-500">{{ $plan->companies_count }} société(s)</p>
                </div>
                <div>
                    <x-input-label :for="'name-'.$plan->id" value="Nom" />
                    <x-text-input :id="'name-'.$plan->id" name="name" class="mt-1 block w-full" :value="$plan->name" required />
                </div>
                <div>
                    <x-input-label :for="'description-'.$plan->id" value="Description" />
                    <x-text-input :id="'description-'.$plan->id" name="description" class="mt-1 block w-full" :value="$plan->description" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label :for="'price-'.$plan->id" value="Prix mensuel" />
                        <x-text-input :id="'price-'.$plan->id" name="monthly_price" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="$plan->monthly_price" required />
                    </div>
                    <div>
                        <x-input-label :for="'currency-'.$plan->id" value="Devise" />
                        <select id="currency-{{ $plan->id }}" name="currency" class="mt-1 block w-full rounded-md border-gray-300">
                            @foreach (['USD', 'CDF'] as $currency)
                                <option value="{{ $currency }}" @selected($plan->currency === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label :for="'users-'.$plan->id" value="Utilisateurs max." />
                        <x-text-input :id="'users-'.$plan->id" name="max_users" type="number" min="1" class="mt-1 block w-full" :value="$plan->max_users" placeholder="Illimité" />
                    </div>
                    <div>
                        <x-input-label :for="'invoices-'.$plan->id" value="Factures / mois max." />
                        <x-text-input :id="'invoices-'.$plan->id" name="max_invoices_per_month" type="number" min="1" class="mt-1 block w-full" :value="$plan->max_invoices_per_month" placeholder="Illimité" />
                    </div>
                </div>
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" value="1" @checked($plan->is_active) class="rounded border-gray-300 text-indigo-600">
                    Proposée aux sociétés
                </label>
                <button type="submit" class="w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Enregistrer</button>
            </form>
        @endforeach
    </div>
@endsection
