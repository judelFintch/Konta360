<?php

use App\Livewire\Actions\Logout;
use App\Modules\Authentication\Enums\AuthenticationCodePurpose;
use App\Modules\Authentication\Services\AuthenticationCodeService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.showcase')] class extends Component
{
    public string $code = '';

    public function mount(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
        }
    }

    /**
     * Confirms the address with the code sent at sign-up (ADR 0004).
     */
    public function verify(AuthenticationCodeService $codes): void
    {
        $this->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => 'Saisissez le code reçu par e-mail.']);
        $user = Auth::user();

        $codes->verify($user, AuthenticationCodePurpose::EmailVerification, $this->code);

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function resend(): void
    {
        $this->resetErrorBag();

        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();
        $this->code = '';
        Session::flash('status', 'Un nouveau code vous a été envoyé.');
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <x-mail-delivery-error />

    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16v12H4zM4 7l8 6 8-6"/></svg>
    </span>
    <h2 class="mt-5 text-2xl font-bold tracking-tight text-gray-900">Confirmez votre adresse e-mail</h2>
    <p class="mt-2 text-sm text-gray-500">
        Votre espace est créé. Pour l’activer, demandez si nécessaire puis saisissez le code à {{ \App\Modules\Authentication\Services\AuthenticationCodeService::LENGTH }} chiffres destiné à
        <span class="font-medium text-gray-900">{{ auth()->user()->email }}</span>.
    </p>

    @if (session('status'))
        <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <form wire:submit="verify" class="mt-6 space-y-5">
        <div>
            <x-input-label for="code" value="Code de confirmation" />
            <x-text-input wire:model="code" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                maxlength="12" placeholder="12345678" required autofocus
                class="mt-1 block w-full py-3 text-center font-mono text-2xl tracking-[0.4em]" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <button type="submit" wire:loading.attr="disabled" class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-70">
            <span wire:loading.remove wire:target="verify">Confirmer mon adresse</span>
            <span wire:loading wire:target="verify">Vérification…</span>
        </button>
    </form>

    <div class="mt-6 flex items-center justify-between text-sm">
        <button type="button" wire:click="resend" wire:loading.attr="disabled" class="font-medium text-indigo-600 hover:text-indigo-500"><span wire:loading.remove wire:target="resend">Renvoyer un code</span><span wire:loading wire:target="resend">Envoi en cours…</span></button>
        <button type="button" wire:click="logout" class="text-gray-500 hover:text-gray-700">Se déconnecter</button>
    </div>

    <p class="mt-6 text-xs text-gray-400">Pensez à vérifier vos courriers indésirables. Le code est valable {{ \App\Modules\Authentication\Services\AuthenticationCodeService::TTL_MINUTES }} minutes.</p>
</div>
