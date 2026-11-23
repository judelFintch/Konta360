<?php

namespace App\Modules\Authentication\Services;

use App\Models\LoginEvent;
use App\Models\User;
use App\Modules\Authentication\Enums\LoginEventType;

/**
 * Writes the sign-in history (see the login_events migration). Never
 * blocks a sign-in: a failure to write is reported and ignored.
 */
class LoginHistory
{
    public function record(User $user, LoginEventType $event, ?string $method = null): void
    {
        try {
            (new LoginEvent)->forceFill([
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'event' => $event,
                'method' => $method,
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 1000),
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
