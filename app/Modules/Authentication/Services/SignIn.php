<?php

namespace App\Modules\Authentication\Services;

use App\Models\User;
use App\Modules\Authentication\Enums\LoginEventType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Last step of every sign-in, whether the code was typed or the browser is
 * trusted (ADR 0004): records the sign-in, opens the session and renews its
 * identifier.
 */
class SignIn
{
    public const BY_CODE = 'code';

    public const BY_TRUSTED_DEVICE = 'trusted_device';

    public function __construct(private readonly LoginHistory $history) {}

    public function complete(User $user, bool $remember, string $method): void
    {
        $user->forceFill(['last_login_at' => now()])->save();
        Auth::login($user, $remember);
        PendingLogin::forget();
        Session::regenerate();
        $this->history->record($user, LoginEventType::SignedIn, $method);
    }
}
