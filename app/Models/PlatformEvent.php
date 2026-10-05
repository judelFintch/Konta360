<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An action taken by a Konta360 operator on a subscriber (ADR 0005). Not
 * company data: the company never sees it.
 */
class PlatformEvent extends Model
{
    public const UPDATED_AT = null;

    public static function record(?User $actor, ?Company $company, string $action, string $description, array $details = []): self
    {
        $event = new self;
        $event->forceFill([
            'actor_id' => $actor?->id,
            'company_id' => $company?->id,
            'action' => $action,
            'description' => $description,
            'details' => $details ?: null,
        ])->save();

        return $event;
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
