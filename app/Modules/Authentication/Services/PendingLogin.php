<?php

namespace App\Modules\Authentication\Services;

use App\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * A sign-in whose password was right and which waits for the emailed code.
 * Kept in the session, never in the browser, and short-lived.
 */
class PendingLogin
{
    private const KEY = 'auth.pending_login';

    public static function start(User $user, bool $remember): void
    {
        Session::put(self::KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
            'expires_at' => now()->addMinutes(AuthenticationCodeService::TTL_MINUTES)->getTimestamp(),
        ]);
    }

    public static function user(): ?User
    {
        $pending = Session::get(self::KEY);
        if (! $pending || $pending['expires_at'] < now()->getTimestamp()) {
            return null;
        }

        return User::find($pending['user_id']);
    }

    public static function remember(): bool
    {
        return (bool) (Session::get(self::KEY)['remember'] ?? false);
    }

    public static function forget(): void
    {
        Session::forget(self::KEY);
    }
}
