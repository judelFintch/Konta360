<?php

use App\Modules\Authentication\Services\TrustedDevices;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Browsers that sign in without a code (ADR 0004, amended).
     */
    public function with(): array
    {
        return [
            'devices' => Auth::user()->trustedDevices()->where('expires_at', '>', now())->latest('last_used_at')->get(),
            'days' => TrustedDevices::days(),
        ];
    }

    public function forget(int $id): void
    {
        Auth::user()->trustedDevices()->whereKey($id)->delete();
        $this->dispatch('trusted-devices-updated');
    }

    public function forgetAll(TrustedDevices $devices): void
    {
        $devices->forgetAll(Auth::user());
        $this->dispatch('trusted-devices-updated');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Appareils de confiance</h2>
        <p class="mt-1 text-sm text-gray-600">
            Sur ces navigateurs, seul votre mot de passe est demandé à la connexion, sans code par e-mail,
            pendant {{ $days }} jours après la dernière saisie d’un code. Changer de mot de passe les oublie tous.
        </p>
    </header>

    @if ($devices->isEmpty())
        <p class="mt-6 text-sm text-gray-500">Aucun appareil de confiance : un code vous est demandé à chaque connexion.</p>
    @else
        <ul class="mt-6 divide-y divide-gray-100 rounded-md border border-gray-200">
            @foreach ($devices as $device)
                <li class="flex items-center justify-between gap-4 px-4 py-3" wire:key="device-{{ $device->id }}">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ $device->label() }}</p>
                        <p class="text-xs text-gray-500">
                            Dernière connexion le {{ $device->last_used_at?->format('d/m/Y à H:i') }}
                            @if ($device->ip_address) · {{ $device->ip_address }}@endif
                            · jusqu’au {{ $device->expires_at->format('d/m/Y') }}
                        </p>
                    </div>
                    <button type="button" wire:click="forget({{ $device->id }})" class="shrink-0 text-sm font-medium text-red-600 hover:text-red-500">Oublier</button>
                </li>
            @endforeach
        </ul>

        <div class="mt-4 flex items-center gap-4">
            <x-danger-button type="button" wire:click="forgetAll" wire:confirm="Oublier tous les appareils ? Un code sera de nouveau demandé à la prochaine connexion sur chacun d’eux.">
                Oublier tous les appareils
            </x-danger-button>
            <x-action-message on="trusted-devices-updated">Fait.</x-action-message>
        </div>
    @endif
</section>
