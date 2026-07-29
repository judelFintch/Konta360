<div class="mb-6 flex flex-wrap gap-2">
    <a href="{{ route('financial-statements.income-statement', request()->only(['date_from', 'date_to', 'currency'])) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ request()->routeIs('financial-statements.income-statement*') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200' }}">Compte de résultat</a>
    <a href="{{ route('financial-statements.balance-sheet', request()->only(['date_to', 'currency'])) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ request()->routeIs('financial-statements.balance-sheet*') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200' }}">Bilan</a>
</div>
