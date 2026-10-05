@props(['inverted' => false])

{{-- Konta360 wordmark, same mark as the company initial in the navigation. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <span class="flex h-9 w-9 items-center justify-center rounded-lg text-sm font-bold {{ $inverted ? 'bg-white text-indigo-700' : 'bg-indigo-600 text-white' }}">K</span>
    <span class="text-lg font-bold tracking-tight {{ $inverted ? 'text-white' : 'text-gray-900' }}">Konta360</span>
</span>
