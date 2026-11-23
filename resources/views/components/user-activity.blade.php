{{-- Whether a user is online now, otherwise when they were last seen. --}}
@props(['at' => null, 'lastLogin' => null])
@php
    $online = $at && $at->gte(now()->subMinutes(\App\Modules\Authentication\Services\UserPresence::ONLINE_MINUTES));
    $seen = collect([$at, $lastLogin])->filter()->max();
@endphp
@if ($online)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700']) }}>
        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>En ligne
    </span>
@elseif ($seen)
    <span {{ $attributes->merge(['class' => 'text-sm text-gray-600']) }} title="{{ $seen->format('d/m/Y à H:i') }}">
        Vu {{ $seen->locale('fr')->diffForHumans() }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'text-sm text-gray-400']) }}>Jamais connecté</span>
@endif
