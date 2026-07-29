@php use App\Modules\Parties\Enums\PartyType; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">Référentiel</p>
                <h1 class="text-2xl font-semibold text-gray-900">Clients et fournisseurs</h1>
            </div>
            <a href="{{ route('parties.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Nouveau tiers
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[1fr_220px_auto]">
                <input name="search" value="{{ $search }}" placeholder="Rechercher par nom, e-mail ou identifiant fiscal"
                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <select name="type" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tous les types</option>
                    @foreach (PartyType::cases() as $partyType)
                        <option value="{{ $partyType->value }}" @selected($type === $partyType->value)>{{ $partyType->label() }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">Filtrer</x-secondary-button>
            </form>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3">Nom</th>
                                <th class="px-6 py-3">Type</th>
                                <th class="px-6 py-3">Contact</th>
                                <th class="px-6 py-3">Statut</th>
                                <th class="px-6 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($parties as $party)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900">{{ $party->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $party->tax_identifier ?: 'Aucun identifiant fiscal' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ $party->type->label() }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <div>{{ $party->email ?: '—' }}</div>
                                        <div class="text-gray-500">{{ $party->phone }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $party->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $party->is_active ? 'Actif' : 'Inactif' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <a href="{{ route('parties.edit', $party) }}" class="font-semibold text-indigo-600 hover:text-indigo-500">Modifier</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-14 text-center">
                                        <p class="font-medium text-gray-900">Aucun tiers trouvé</p>
                                        <p class="mt-1 text-sm text-gray-500">Créez votre premier client ou fournisseur.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($parties->hasPages())
                    <div class="border-t border-gray-200 px-6 py-4">{{ $parties->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
