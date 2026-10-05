<?php

namespace App\Modules\Billing\Enums;

enum SubscriptionPaymentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de vérification',
            self::Confirmed => 'Confirmé',
            self::Rejected => 'Refusé',
        };
    }
}
