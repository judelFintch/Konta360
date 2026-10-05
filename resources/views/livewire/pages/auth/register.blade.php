<?php

use App\Models\Plan;
use App\Models\User;
use App\Modules\Companies\Services\CompanyProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $company_name = '';
    public string $default_currency = 'CDF';
    public bool $terms = false;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Creates a new company with its chart of accounts, its free evaluation
     * month and its first administrator (ADR 0002 § 9, ADR 0003 § 1).
     */
    public function register(CompanyProvisioner $provisioner): void
    {
        $validated = $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'default_currency' => ['required', 'in:CDF,USD'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Vous devez accepter les conditions générales et la politique de confidentialité.']);

        [, $user] = $provisioner->register(
            ['name' => $validated['company_name'], 'default_currency' => $validated['default_currency']],
            ['name' => $validated['name'], 'email' => $validated['email'], 'password' => $validated['password']],
            Plan::evaluation(),
        );

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h2 class="text-2xl font-bold tracking-tight text-gray-900">Créer votre espace</h2>
    <p class="mt-1 text-sm text-gray-500">Votre société dispose de sa propre comptabilité et de ses propres utilisateurs.</p>
    <p class="mt-4 rounded-lg bg-indigo-50 p-3 text-sm text-indigo-800">
        Évaluation gratuite d’un mois, avec tous les modules et sans engagement.
        Vous choisirez ensuite la formule qui vous convient.
        <a href="{{ route('pricing') }}" target="_blank" class="font-semibold underline">Voir les formules</a>
    </p>

    <form wire:submit="register" class="mt-8 space-y-5">
        <fieldset class="space-y-5">
            <legend class="text-sm font-semibold text-gray-900">Société</legend>

            <div>
                <x-input-label for="company_name" value="Raison sociale" />
                <x-text-input wire:model="company_name" id="company_name" class="block mt-1 w-full py-2.5" type="text" name="company_name" required autofocus autocomplete="organization" />
                <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="default_currency" value="Devise principale" />
                <select wire:model="default_currency" id="default_currency" name="default_currency" class="mt-1 block w-full rounded-md border-gray-300 py-2.5 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="CDF">CDF — Franc congolais</option>
                    <option value="USD">USD — Dollar américain</option>
                </select>
                <x-input-error :messages="$errors->get('default_currency')" class="mt-2" />
            </div>
        </fieldset>

        <fieldset class="space-y-5 border-t border-gray-200 pt-5">
            <legend class="text-sm font-semibold text-gray-900">Administrateur</legend>

            <div>
                <x-input-label for="name" value="Nom complet" />
                <x-text-input wire:model="name" id="name" class="block mt-1 w-full py-2.5" type="text" name="name" required autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" value="Adresse e-mail" />
                <x-text-input wire:model="email" id="email" class="block mt-1 w-full py-2.5" type="email" name="email" placeholder="vous@entreprise.com" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" value="Mot de passe" />
                <x-text-input wire:model="password" id="password" class="block mt-1 w-full py-2.5" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Confirmation du mot de passe" />
                <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full py-2.5" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </fieldset>

        <div>
            <label for="terms" class="flex items-start gap-2 text-sm text-gray-600">
                <input wire:model="terms" id="terms" name="terms" type="checkbox" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span>J’accepte les <a href="{{ route('legal.terms') }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">conditions générales d’utilisation</a> et la <a href="{{ route('legal.privacy') }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">politique de confidentialité</a>.</span>
            </label>
            <x-input-error :messages="$errors->get('terms')" class="mt-2" />
        </div>

        <button type="submit" wire:loading.attr="disabled" class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-70">
            <span wire:loading.remove wire:target="register">Créer mon espace</span>
            <span wire:loading wire:target="register">Création en cours…</span>
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-gray-500">
        Déjà inscrit ?
        <a class="font-medium text-indigo-600 hover:text-indigo-500" href="{{ route('login') }}" wire:navigate>Se connecter</a>
    </p>
</div>
