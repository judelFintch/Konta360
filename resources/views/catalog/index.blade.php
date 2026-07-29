@php use App\Modules\Catalog\Enums\ItemType; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">Facturation</p>
                <h1 class="text-2xl font-semibold text-gray-900">Produits et services</h1>
                <p class="mt-1 text-sm text-gray-500">Le catalogue des articles facturables : prix, devise et taxe par défaut, réutilisés dans devis et factures.</p>
            </div>
            <a href="{{ route('catalog.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Nouvel article
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[1fr_220px_auto]">
                <input name="search" value="{{ $search }}" placeholder="Rechercher par désignation ou référence"
                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <select name="type" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tous les types</option>
                    @foreach (ItemType::cases() as $itemType)
                        <option value="{{ $itemType->value }}" @selected($type === $itemType->value)>{{ $itemType->label() }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">Filtrer</x-secondary-button>
            </form>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3">Article</th>
                                <th class="px-6 py-3">Type</th>
                                <th class="px-6 py-3">Prix HT</th>
                                <th class="px-6 py-3">Taxe</th>
                                <th class="px-6 py-3">Statut</th>
                                <th class="px-6 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($items as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900">{{ $item->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $item->sku }} · {{ $item->unit }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ $item->type->label() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                        {{ number_format((float) $item->unit_price, 2, ',', ' ') }} {{ $item->currency }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ number_format((float) $item->tax_rate, 2, ',', ' ') }} %</td>
                                    <td class="px-6 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $item->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $item->is_active ? 'Actif' : 'Inactif' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <a href="{{ route('catalog.edit', $item) }}" class="font-semibold text-indigo-600 hover:text-indigo-500">Modifier</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-14 text-center">
                                        <p class="font-medium text-gray-900">Aucun article trouvé</p>
                                        <p class="mt-1 text-sm text-gray-500">Créez votre premier produit ou service.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($items->hasPages())
                    <div class="border-t border-gray-200 px-6 py-4">{{ $items->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
