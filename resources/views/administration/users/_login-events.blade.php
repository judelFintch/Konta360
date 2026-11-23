{{-- Sign-in history table. Expects $events; $showUser adds the user column. --}}
@php $showUser ??= false; @endphp
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-5 py-3">Date</th>
                @if ($showUser)<th class="px-5 py-3">Utilisateur</th>@endif
                <th class="px-5 py-3">Événement</th>
                <th class="px-5 py-3">Appareil</th>
                <th class="px-5 py-3">Adresse IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-sm">
            @forelse ($events as $event)
                <tr class="{{ $event->event->isFailure() ? 'bg-red-50/40' : '' }}">
                    <td class="whitespace-nowrap px-5 py-3 text-gray-700">{{ $event->created_at->format('d/m/Y à H:i') }}</td>
                    @if ($showUser)
                        <td class="px-5 py-3">
                            <a href="{{ route('administration.logins.index', ['user_id' => $event->user_id]) }}" class="font-medium text-gray-900 hover:text-indigo-600" wire:navigate>{{ $event->user?->name }}</a>
                            <p class="text-xs text-gray-500">{{ $event->user?->email }}</p>
                        </td>
                    @endif
                    <td class="px-5 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $event->event->badge() }}">{{ $event->event->label() }}</span>
                        @if ($event->methodLabel())<span class="ms-1 text-xs text-gray-500">par {{ $event->methodLabel() }}</span>@endif
                    </td>
                    <td class="px-5 py-3 text-gray-600">{{ $event->deviceLabel() }}</td>
                    <td class="px-5 py-3 font-mono text-xs text-gray-600">{{ $event->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $showUser ? 5 : 4 }}" class="px-5 py-10 text-center text-gray-500">Aucune connexion enregistrée.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
