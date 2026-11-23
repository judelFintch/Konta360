<?php

namespace App\Models;

use App\Modules\Authentication\Enums\LoginEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the sign-in history. Written before any company is current
 * (at sign-in), so it is not confined by the company scope: screens filter
 * explicitly with forCompany() or by user. Filled with forceFill only.
 */
class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event' => LoginEventType::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForCompany(Builder $query, Company $company): void
    {
        $query->where('company_id', $company->id);
    }

    public function methodLabel(): ?string
    {
        return match ($this->method) {
            'code' => 'code par e-mail',
            'trusted_device' => 'appareil de confiance',
            default => null,
        };
    }

    /**
     * Short readable name of the browser, as for trusted devices.
     */
    public function deviceLabel(): string
    {
        return (new TrustedDevice)->forceFill(['user_agent' => $this->user_agent])->label();
    }
}
