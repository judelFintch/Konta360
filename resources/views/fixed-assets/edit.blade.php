<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">{{ $asset->code }}</p><h1 class="text-2xl font-semibold text-gray-900">Modifier l’immobilisation</h1><p class="mt-1 text-sm text-gray-500">Les échéances déjà comptabilisées ne sont pas recalculées.</p></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8"><form method="POST" action="{{ route('fixed-assets.update', $asset) }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">@csrf @method('PUT') @include('fixed-assets._form', ['submitLabel' => 'Enregistrer'])</form></div></div>
</x-app-layout>
