<?php

namespace App\Modules\Administration\Enums;

/**
 * Stable technical role keys. Application code (Gates, Policies, route middleware)
 * must never check ->hasRole() against these — always gate on a Permission case.
 * These exist only for seeding, the settings.manage UI, and display labels.
 */
enum Role: string
{
    case Administrateur = 'administrateur';
    case Comptable = 'comptable';
    case Direction = 'direction';
    case Commercial = 'commercial';

    /**
     * Label suitable for display in the UI (French, per cahier des charges terminology).
     */
    public function label(): string
    {
        return match ($this) {
            self::Administrateur => 'Administrateur / Responsable comptable',
            self::Comptable => 'Comptable',
            self::Direction => 'Direction / Gérance',
            self::Commercial => 'Utilisateur commercial',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
