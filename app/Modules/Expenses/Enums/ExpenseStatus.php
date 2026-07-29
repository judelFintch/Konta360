<?php

namespace App\Modules\Expenses\Enums;

enum ExpenseStatus: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Validated => 'Validée',
            self::Paid => 'Payée',
            self::Cancelled => 'Annulée',
        };
    }
}
