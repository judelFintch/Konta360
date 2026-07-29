<?php

namespace App\Modules\FixedAssets\Enums;

enum AssetCategory: string
{
    case Building = 'building';
    case Vehicle = 'vehicle';
    case Equipment = 'equipment';
    case Computer = 'computer';
    case Furniture = 'furniture';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Building => 'Bâtiment',
            self::Vehicle => 'Véhicule',
            self::Equipment => 'Matériel et équipement',
            self::Computer => 'Matériel informatique',
            self::Furniture => 'Mobilier',
            self::Other => 'Autre immobilisation',
        };
    }
}
