<?php

namespace App\Models;

use App\Modules\Authentication\Enums\AuthenticationCodePurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code sent by email. Belongs to a user, not to a company: it is
 * used before any company context exists (ADR 0004).
 */
class AuthenticationCode extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }

    protected function casts(): array
    {
        return [
            'purpose' => AuthenticationCodePurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
