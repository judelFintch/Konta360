{{-- Fiche client résumée. Paramètre : $party. --}}
<article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Client</p>
    <p class="mt-2 font-semibold text-gray-900">{{ $party->name }}</p>
    <dl class="mt-2 space-y-1 text-sm text-gray-600">
        @if ($party->tax_identifier)<div><span class="text-gray-400">NIF</span> {{ $party->tax_identifier }}</div>@endif
        @if ($party->address)<div class="whitespace-pre-line">{{ $party->address }}</div>@endif
        @if ($party->email)<div><a href="mailto:{{ $party->email }}" class="text-indigo-600 hover:text-indigo-500">{{ $party->email }}</a></div>@endif
        @if ($party->phone)<div>{{ $party->phone }}</div>@endif
    </dl>
</article>
