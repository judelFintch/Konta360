<?php

namespace App\Modules\Billing\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Exempt = 'exempt';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Essai gratuit',
            self::Active => 'Abonnement actif',
            self::Exempt => 'Accès offert',
            self::Expired => 'Expiré — lecture seule',
        };
    }

    /**
     * An expired company keeps reading and exporting its data but can no
     * longer create or change anything (ADR 0003 § 3).
     */
    public function allowsWriting(): bool
    {
        return $this !== self::Expired;
    }
}
