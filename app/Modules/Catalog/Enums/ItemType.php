<?php

namespace App\Modules\Catalog\Enums;

enum ItemType: string
{
    case Product = 'product';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Produit',
            self::Service => 'Service',
        };
    }
}
