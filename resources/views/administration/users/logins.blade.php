<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">Administration</p>
                <h1 class="text-2xl font-semibold text-gray-900">Historique des connexions</h1>
                <p class="mt-1 text-sm text-gray-500">Connexions, déconnexions et tentatives refusées des utilisateurs de la société.</p>
            </div>
            <a href="{{ route('administration.users.index') }}" class="text-sm font-semibold text-indigo-600" wire:navigate>← Utilisateurs</a>
        </div>
    </x-slot>

    <div class="py-10"><div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 sm:grid-cols-5">
            <select name="user_id" class="rounded-md border-gray-300 text-sm" aria-label="Utilisateur">
                <option value="">Tous les utilisateurs</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) ($filters['user_id'] ?? 0) === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            <select name="event" class="rounded-md border-gray-300 text-sm" aria-label="Événement">
                <option value="">Tous les événements</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($filters['event'] ?? null) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-md border-gray-300 text-sm" aria-label="Du">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-md border-gray-300 text-sm" aria-label="Au">
            <x-secondary-button type="submit" class="justify-center">Filtrer</x-secondary-button>
        </form>
        <x-input-error :messages="$errors->all()" class="mb-4" />

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            @include('administration.users._login-events', ['events' => $events, 'showUser' => true])
            @if ($events->hasPages())
                <div class="border-t px-6 py-4">{{ $events->links() }}</div>
            @endif
        </div>
    </div></div>
</x-app-layout>
