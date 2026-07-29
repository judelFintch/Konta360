<?php

namespace App\Modules\Parties\Enums;

enum PartyType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Client',
            self::Supplier => 'Fournisseur',
            self::Both => 'Client et fournisseur',
        };
    }
}
