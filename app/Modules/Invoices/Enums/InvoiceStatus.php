<?php

namespace App\Modules\Invoices\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Validated => 'Validée',
            self::Cancelled => 'Annulée',
        };
    }
}
