<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Checks the credentials and returns the user, without signing in: the
     * sign-in is completed by the code sent by email (ADR 0004).
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials(['email' => $this->email]);

        if (! $user || ! $provider->validateCredentials($user, ['password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        self::ensureCanSignIn($user, 'form.email');

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * Checked again when the code is entered: the account or its company
     * may have been disabled in between.
     *
     * @throws ValidationException
     */
    public static function ensureCanSignIn(User $user, string $field): void
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages([$field => __('Ce compte utilisateur est désactivé.')]);
        }

        if ($user->company?->isSuspended()) {
            throw ValidationException::withMessages([$field => __('L’accès de votre société est suspendu. Contactez le support Konta360.')]);
        }
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
