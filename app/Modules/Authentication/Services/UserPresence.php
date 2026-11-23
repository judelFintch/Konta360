<?php

namespace App\Modules\Authentication\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who is using the application right now, read from the database sessions
 * (SESSION_DRIVER=database): a user is « online » when one of their sessions
 * was active in the last few minutes. With another session driver nobody
 * can be seen online and the screens fall back to the last sign-in.
 */
class UserPresence
{
    public const ONLINE_MINUTES = 5;

    public static function available(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Last activity per user id, for the given users.
     *
     * @param  iterable<int>  $userIds
     * @return Collection<int, CarbonImmutable>
     */
    public function lastActivity(iterable $userIds): Collection
    {
        $ids = collect($userIds)->filter()->unique()->values();
        if (! self::available() || $ids->isEmpty()) {
            return collect();
        }

        return DB::table(config('session.table', 'sessions'))
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->selectRaw('user_id, max(last_activity) as last_activity')
            ->pluck('last_activity', 'user_id')
            ->map(fn ($timestamp) => CarbonImmutable::createFromTimestamp((int) $timestamp));
    }

    /**
     * @param  iterable<int>  $userIds
     * @return Collection<int, CarbonImmutable> only the users online now
     */
    public function online(iterable $userIds): Collection
    {
        $threshold = now()->subMinutes(self::ONLINE_MINUTES);

        return $this->lastActivity($userIds)->filter(fn (CarbonImmutable $at) => $at->gte($threshold));
    }
}
