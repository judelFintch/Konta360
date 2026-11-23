<?php

namespace App\Modules\Authentication\Enums;

enum LoginEventType: string
{
    case SignedIn = 'signed_in';
    case WrongPassword = 'wrong_password';
    case WrongCode = 'wrong_code';
    case SignedOut = 'signed_out';

    public function label(): string
    {
        return match ($this) {
            self::SignedIn => 'Connexion',
            self::WrongPassword => 'Mot de passe erroné',
            self::WrongCode => 'Code refusé',
            self::SignedOut => 'Déconnexion',
        };
    }

    public function isFailure(): bool
    {
        return in_array($this, [self::WrongPassword, self::WrongCode], true);
    }

    /** Tailwind classes of the badge. */
    public function badge(): string
    {
        return match ($this) {
            self::SignedIn => 'bg-emerald-50 text-emerald-700',
            self::SignedOut => 'bg-gray-100 text-gray-600',
            default => 'bg-red-50 text-red-700',
        };
    }
}
