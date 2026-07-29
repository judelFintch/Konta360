<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">Ventes</p>
            <h1 class="text-2xl font-semibold text-gray-900">Nouveau devis</h1>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('quotes.store') }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                @csrf
                @include('quotes._form', ['quote' => null, 'submitLabel' => 'Enregistrer le devis'])
            </form>
        </div>
    </div>
</x-app-layout>
