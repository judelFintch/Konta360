<div class="mb-6 flex flex-wrap gap-2">
    <a href="{{ route('accounting.entries.index') }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ request()->routeIs('accounting.entries.*') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200' }}">Journal</a>
    <a href="{{ route('accounting.trial-balance', request()->only(['date_from', 'date_to', 'currency'])) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ request()->routeIs('accounting.trial-balance*') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200' }}">Balance générale</a>
    <a href="{{ route('accounting.ledger', request()->only(['date_from', 'date_to', 'currency'])) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ request()->routeIs('accounting.ledger') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200' }}">Grand livre</a>
</div>
