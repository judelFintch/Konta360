<?php

use App\Models\LoginEvent;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public function with(): array
    {
        return [
            'events' => LoginEvent::query()->where('user_id', Auth::id())->latest('created_at')->latest('id')->limit(15)->get(),
        ];
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Mes dernières connexions</h2>
        <p class="mt-1 text-sm text-gray-600">
            Vérifiez que vous reconnaissez chaque connexion. En cas de doute, changez votre mot de passe :
            cela oublie aussi vos appareils de confiance.
        </p>
    </header>

    <div class="mt-6 overflow-hidden rounded-md border border-gray-200">
        @include('administration.users._login-events', ['events' => $events])
    </div>
</section>
