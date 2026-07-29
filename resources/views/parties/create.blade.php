<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">Tiers</p>
            <h1 class="text-2xl font-semibold text-gray-900">Nouveau client ou fournisseur</h1>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('parties.store') }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                @csrf
                @include('parties._form', ['party' => null, 'submitLabel' => 'Créer le tiers'])
            </form>
        </div>
    </div>
</x-app-layout>
