<?php

use App\Livewire\Forms\LoginForm;
use App\Modules\Authentication\Enums\AuthenticationCodePurpose;
use App\Modules\Authentication\Services\AuthenticationCodeService;
use App\Modules\Authentication\Services\PendingLogin;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.showcase')] class extends Component
{
    public LoginForm $form;

    /**
     * First step: the password. The sign-in is completed on the next page
     * with the code sent by email (ADR 0004).
     */
    public function login(AuthenticationCodeService $codes): void
    {
        $this->validate();

        $user = $this->form->authenticate();
        PendingLogin::start($user, $this->form->remember);

        try {
            $codes->send($user, AuthenticationCodePurpose::Login);
        } catch (ValidationException) {
            // A code was sent less than a minute ago: it is still valid.
        }

        $this->redirectRoute('login.code', navigate: true);
    }
}; ?>

<div>
    <h2 class="text-2xl font-bold tracking-tight text-gray-900">Connexion</h2>
    <p class="mt-1 text-sm text-gray-500">Accédez à votre espace de gestion comptable.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form wire:submit="login" class="mt-8 space-y-5">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full py-2.5" type="email" name="email" placeholder="vous@entreprise.com" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" value="Mot de passe" />
                @if (Route::has('password.request'))
                    <a class="text-sm font-medium text-indigo-600 hover:text-indigo-500 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}" wire:navigate>
                        Mot de passe oublié ?
                    </a>
                @endif
            </div>

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full py-2.5"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <label for="remember" class="inline-flex items-center">
            <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
            <span class="ms-2 text-sm text-gray-600">Rester connecté</span>
        </label>

        <button type="submit" wire:loading.attr="disabled" class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-70">
            <span wire:loading.remove wire:target="login">Se connecter</span>
            <span wire:loading wire:target="login">Connexion en cours…</span>
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-gray-500">
        Nouvelle société ?
        <a class="font-medium text-indigo-600 hover:text-indigo-500" href="{{ route('register') }}" wire:navigate>Créer votre espace</a>
        · <a class="font-medium text-indigo-600 hover:text-indigo-500" href="{{ route('pricing') }}">Formules et tarifs</a>
    </p>
    <p class="mt-2 text-center text-xs text-gray-400">
        Vous rejoignez une société existante ? Demandez un compte à son administrateur.
    </p>
</div>
