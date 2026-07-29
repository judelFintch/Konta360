<?php

namespace App\Modules\Payments\Enums;

enum PaymentStatus: string
{
    case Recorded = 'recorded';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Enregistré',
            self::Reversed => 'Annulé',
        };
    }
}
