<x-app-layout>
    <x-slot name="header"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-medium text-indigo-600">Administration</p><h1 class="text-2xl font-semibold text-gray-900">Utilisateurs et rôles</h1></div><a href="{{ route('administration.users.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Nouvel utilisateur</a></div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        <form method="GET" class="mb-6 flex gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200"><input name="search" value="{{ $search }}" placeholder="Nom ou adresse e-mail" class="min-w-0 flex-1 rounded-md border-gray-300"><x-secondary-button type="submit">Rechercher</x-secondary-button></form>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-5 py-3">Utilisateur</th><th class="px-5 py-3">Rôle</th><th class="px-5 py-3">Statut</th><th class="px-5 py-3">Dernière modification</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($users as $managedUser)
                    <tr><td class="px-5 py-4"><p class="font-semibold">{{ $managedUser->name }}</p><p class="text-sm text-gray-500">{{ $managedUser->email }}</p></td><td class="px-5 py-4">{{ $managedUser->roles->first()?->name ?? 'Sans rôle' }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $managedUser->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $managedUser->is_active ? 'Actif' : 'Désactivé' }}</span></td><td class="px-5 py-4 text-sm">{{ $managedUser->updated_at->format('d/m/Y H:i') }}</td><td class="px-5 py-4 text-right"><a href="{{ route('administration.users.edit',$managedUser) }}" class="font-semibold text-indigo-600">Gérer</a></td></tr>
                @endforeach
            </tbody>
        </table></div>
        @if ($users->hasPages())
            <div class="border-t px-6 py-4">{{ $users->links() }}</div>
        @endif
        </div>
    </div></div>
</x-app-layout>
