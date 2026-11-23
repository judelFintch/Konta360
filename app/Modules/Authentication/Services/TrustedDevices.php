<?php

namespace App\Modules\Authentication\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * « Trusted device » (ADR 0004, amended): once a user has entered a valid
 * sign-in code on a browser and ticked the box, that browser signs in with
 * the password alone for a few days. The password is always required.
 *
 * The browser holds « user id|random token » in an encrypted, HTTP-only
 * cookie; the database only keeps a SHA-256 hash of the token. Changing
 * the password forgets every device of the user (see User::booted).
 */
class TrustedDevices
{
    public const COOKIE = 'konta360_trusted_device';

    public static function days(): int
    {
        return max(0, (int) config('konta360.trusted_device_days'));
    }

    public static function enabled(): bool
    {
        return self::days() > 0;
    }

    /**
     * Remembers the current browser for the user and queues its cookie.
     */
    public function trust(User $user, Request $request): void
    {
        if (! self::enabled()) {
            return;
        }

        // A browser holds one trusted sign-in at a time: replacing it here
        // also drops the previous one from the database.
        $this->forgetCurrent($request);
        TrustedDevice::query()->where('user_id', $user->id)->where('expires_at', '<=', now())->delete();

        $token = Str::random(64);
        $expiresAt = now()->addDays(self::days());
        (new TrustedDevice)->forceFill([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => $expiresAt,
        ])->save();

        Cookie::queue(Cookie::make(
            self::COOKIE,
            $user->id.'|'.$token,
            minutes: self::days() * 24 * 60,
            secure: config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    /**
     * Whether the current browser is trusted for this user. Never extends
     * the trust: the code is asked again once it expires.
     */
    public function recognizes(User $user, Request $request): bool
    {
        if (! self::enabled() || ! ($device = $this->current($request)) || $device->user_id !== $user->id) {
            return false;
        }

        $device->forceFill(['last_used_at' => now(), 'ip_address' => $request->ip()])->save();

        return true;
    }

    public function forgetCurrent(Request $request): void
    {
        $this->current($request, withExpired: true)?->delete();
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public function forgetAll(User $user): int
    {
        return TrustedDevice::query()->where('user_id', $user->id)->delete();
    }

    private function current(Request $request, bool $withExpired = false): ?TrustedDevice
    {
        $value = (string) $request->cookie(self::COOKIE);
        if (! preg_match('/^(\d+)\|([A-Za-z0-9]{64})$/', $value, $matches)) {
            return null;
        }

        return TrustedDevice::query()
            ->where('user_id', (int) $matches[1])
            ->where('token_hash', hash('sha256', $matches[2]))
            ->when(! $withExpired, fn ($query) => $query->where('expires_at', '>', now()))
            ->first();
    }
}
