<?php

namespace App\Modules\Treasury\Enums;

enum TreasuryTransactionType: string
{
    case Inflow = 'inflow';
    case Outflow = 'outflow';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Inflow => 'Entrée',
            self::Outflow => 'Sortie',
            self::Transfer => 'Virement interne',
        };
    }
}
