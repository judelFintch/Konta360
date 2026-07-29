<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Immobilisations</p><h1 class="text-2xl font-semibold text-gray-900">Nouvelle immobilisation</h1><p class="mt-1 text-sm text-gray-500">Déclarez un bien acquis pour générer automatiquement son plan d'amortissement linéaire.</p></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8"><form method="POST" action="{{ route('fixed-assets.store') }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">@csrf @include('fixed-assets._form', ['asset' => null, 'submitLabel' => 'Créer l’immobilisation'])</form></div></div>
</x-app-layout>
