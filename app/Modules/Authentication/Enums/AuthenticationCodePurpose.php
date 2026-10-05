<?php

namespace App\Modules\Authentication\Enums;

enum AuthenticationCodePurpose: string
{
    /** Confirms the email address given at sign-up. */
    case EmailVerification = 'email_verification';

    /** Second step of every sign-in, after the password. */
    case Login = 'login';

    public function subject(): string
    {
        return match ($this) {
            self::EmailVerification => 'Confirmez votre adresse e-mail',
            self::Login => 'Votre code de connexion',
        };
    }

    public function intro(): string
    {
        return match ($this) {
            self::EmailVerification => 'Bienvenue sur Konta360. Pour confirmer votre adresse e-mail et activer votre espace, saisissez ce code :',
            self::Login => 'Pour terminer votre connexion à Konta360, saisissez ce code :',
        };
    }
}
