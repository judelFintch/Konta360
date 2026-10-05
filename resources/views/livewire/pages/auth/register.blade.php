<?php

use App\Models\Plan;
use App\Models\User;
use App\Modules\Companies\Services\CompanyProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.register')] class extends Component
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
    <h2 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Créer votre espace</h2>
    <p class="mt-2 text-sm text-gray-600">
        Votre société disposera de sa propre comptabilité et de ses propres utilisateurs.
        Déjà inscrit&nbsp;?
        <a class="font-medium text-indigo-600 hover:text-indigo-500" href="{{ route('login') }}" wire:navigate>Se connecter</a>
    </p>

    {{-- On large screens the side panel already says this. --}}
    <p class="mt-4 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800 lg:hidden">
        Un mois d’évaluation gratuit, avec tous les modules et sans engagement.
        <a href="{{ route('pricing') }}" class="font-semibold underline">Voir les formules</a>
    </p>

    <form wire:submit="register" class="mt-8 space-y-6">
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <div class="flex items-center gap-3">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">1</span>
                <div>
                    <h3 class="font-semibold text-gray-900">Votre société</h3>
                    <p class="text-xs text-gray-500">Vous compléterez ses informations légales plus tard, dans les paramètres.</p>
                </div>
            </div>

            <div class="mt-6 space-y-5">
                <div>
                    <x-input-label for="company_name" value="Raison sociale" />
                    <x-text-input wire:model="company_name" id="company_name" class="mt-1 block w-full py-2.5" type="text" name="company_name" placeholder="Ex. Kivu Services SARL" required autofocus autocomplete="organization" />
                    <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                </div>

                <fieldset>
                    <legend class="text-sm font-medium text-gray-700">Devise principale</legend>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        @foreach (['CDF' => 'Franc congolais', 'USD' => 'Dollar américain'] as $code => $label)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 transition has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50 has-[:checked]:ring-1 has-[:checked]:ring-indigo-600 hover:border-gray-400">
                                <input wire:model="default_currency" type="radio" name="default_currency" value="{{ $code }}" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">{{ $code }}</span>
                                    <span class="block text-xs text-gray-500">{{ $label }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Proposée par défaut sur vos documents ; chaque document peut être émis en CDF ou en USD.</p>
                    <x-input-error :messages="$errors->get('default_currency')" class="mt-2" />
                </fieldset>
            </div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
            <div class="flex items-center gap-3">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">2</span>
                <div>
                    <h3 class="font-semibold text-gray-900">Votre compte administrateur</h3>
                    <p class="text-xs text-gray-500">Vous pourrez ensuite inviter vos collaborateurs.</p>
                </div>
            </div>

            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Nom complet" />
                    <x-text-input wire:model="name" id="name" class="mt-1 block w-full py-2.5" type="text" name="name" required autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" value="Adresse e-mail" />
                    <x-text-input wire:model="email" id="email" class="mt-1 block w-full py-2.5" type="email" name="email" placeholder="vous@entreprise.com" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" value="Mot de passe" />
                    <x-text-input wire:model="password" id="password" class="mt-1 block w-full py-2.5" type="password" name="password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" value="Confirmation" />
                    <x-text-input wire:model="password_confirmation" id="password_confirmation" class="mt-1 block w-full py-2.5" type="password" name="password_confirmation" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                <p class="-mt-2 text-xs text-gray-500 sm:col-span-2">Au moins 8 caractères. Un e-mail de confirmation vous sera envoyé.</p>
            </div>
        </section>

        <div>
            <label for="terms" class="flex items-start gap-3 text-sm text-gray-600">
                <input wire:model="terms" id="terms" name="terms" type="checkbox" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span>J’accepte les <a href="{{ route('legal.terms') }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">conditions générales d’utilisation</a> et la <a href="{{ route('legal.privacy') }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">politique de confidentialité</a>.</span>
            </label>
            <x-input-error :messages="$errors->get('terms')" class="mt-2" />
        </div>

        <button type="submit" wire:loading.attr="disabled" class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-70">
            <span wire:loading.remove wire:target="register">Créer mon espace et commencer l’évaluation</span>
            <span wire:loading wire:target="register">Création de votre espace…</span>
        </button>
    </form>
</div>
