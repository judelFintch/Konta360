<?php

namespace App\Modules\Catalog\Enums;

/**
 * Standard units offered in the catalog. The code is stored; documents
 * print its translation. Any other text typed by the user is printed as is.
 */
enum Unit: string
{
    case Unit = 'unit';
    case Piece = 'piece';
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case FlatRate = 'flat_rate';
    case Trip = 'trip';
    case Kilometre = 'km';
    case Metre = 'm';
    case SquareMetre = 'm2';
    case CubicMetre = 'm3';
    case Kilogram = 'kg';
    case Tonne = 't';
    case Litre = 'l';
    case Box = 'box';
    case Lot = 'lot';

    /** Value of the form option that reveals the free text field. */
    public const OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Unité',
            self::Piece => 'Pièce',
            self::Hour => 'Heure (h)',
            self::Day => 'Jour',
            self::Week => 'Semaine',
            self::Month => 'Mois',
            self::Year => 'Année',
            self::FlatRate => 'Forfait',
            self::Trip => 'Voyage',
            self::Kilometre => 'Kilomètre (km)',
            self::Metre => 'Mètre (m)',
            self::SquareMetre => 'Mètre carré (m²)',
            self::CubicMetre => 'Mètre cube (m³)',
            self::Kilogram => 'Kilogramme (kg)',
            self::Tonne => 'Tonne (t)',
            self::Litre => 'Litre (L)',
            self::Box => 'Carton',
            self::Lot => 'Lot',
        };
    }

    /**
     * What a document prints for a stored unit, in the document language.
     */
    public static function display(?string $unit, string $locale = 'fr'): string
    {
        $standard = self::tryFrom((string) $unit);

        return $standard ? __("document.units.{$standard->value}", [], $locale) : (string) $unit;
    }
}
