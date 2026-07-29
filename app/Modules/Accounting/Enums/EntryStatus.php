<?php

namespace App\Modules\Accounting\Enums;

enum EntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Posted => 'Comptabilisée',
        };
    }
}
