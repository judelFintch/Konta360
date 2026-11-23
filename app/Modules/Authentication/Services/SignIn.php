<?php

namespace App\Modules\Authentication\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Last step of every sign-in, whether the code was typed or the browser is
 * trusted (ADR 0004): records the sign-in, opens the session and renews its
 * identifier.
 */
class SignIn
{
    public function complete(User $user, bool $remember): void
    {
        $user->forceFill(['last_login_at' => now()])->save();
        Auth::login($user, $remember);
        PendingLogin::forget();
        Session::regenerate();
    }
}
