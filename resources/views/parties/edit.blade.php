<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">Tiers</p>
            <h1 class="text-2xl font-semibold text-gray-900">Modifier {{ $party->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Mettez à jour ses coordonnées ; les documents déjà émis ne sont pas modifiés rétroactivement.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('parties.update', $party) }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                @csrf
                @method('PUT')
                @include('parties._form', ['submitLabel' => 'Enregistrer'])
            </form>
        </div>
    </div>
</x-app-layout>
