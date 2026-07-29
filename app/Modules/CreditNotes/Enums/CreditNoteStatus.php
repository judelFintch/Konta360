<?php

namespace App\Modules\CreditNotes\Enums;

enum CreditNoteStatus: string
{
    case Issued = 'issued';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Émis',
        };
    }
}
