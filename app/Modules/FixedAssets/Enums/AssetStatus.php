<?php

namespace App\Modules\FixedAssets\Enums;

enum AssetStatus: string
{
    case Active = 'active';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'En service',
            self::Disposed => 'Sortie',
        };
    }
}
