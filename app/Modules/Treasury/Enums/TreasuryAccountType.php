<?php

namespace App\Modules\Treasury\Enums;

enum TreasuryAccountType: string
{
    case Bank = 'bank';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Compte bancaire',
            self::Cash => 'Caisse',
        };
    }

    public function accountingCode(): string
    {
        return match ($this) {
            self::Bank => '512',
            self::Cash => '571',
        };
    }
}
