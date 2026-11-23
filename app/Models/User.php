<?php

namespace App\Models;

use App\Modules\Authentication\Enums\AuthenticationCodePurpose;
use App\Modules\Authentication\Services\AuthenticationCodeService;
use App\Modules\Authentication\Services\TrustedDevices;
use App\Modules\Companies\Services\PlatformCompanyAccess;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable {
        hasPermissionTo as protected hasPermissionToWithRoles;
    }

    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        if (app(PlatformCompanyAccess::class)->grantsPermission($this, $permission)) {
            return true;
        }

        return $this->hasPermissionToWithRoles($permission, $guardName);
    }

    /**
     * Users are not filtered by a global scope: authentication must find a
     * user before any company is known. Screens listing users filter on the
     * company explicitly. Platform administrators belong to no company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Sends an eight-digit code instead of the default signed link
     * (ADR 0004). Called by Laravel when a user registers.
     */
    public function sendEmailVerificationNotification(): void
    {
        app(AuthenticationCodeService::class)->send($this, AuthenticationCodePurpose::EmailVerification);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * A new password, whoever sets it (the user, an administrator, the
     * platform, a reset link), withdraws the trust given to every browser:
     * the next sign-in asks for a code again everywhere.
     */
    protected static function booted(): void
    {
        static::updated(function (self $user) {
            if ($user->wasChanged('password')) {
                app(TrustedDevices::class)->forgetAll($user);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
