<?php

namespace App\Modules\Documents\Enums;

/**
 * Language a commercial document is printed in. It only affects the
 * presentation: amounts, numbering and the control code do not change.
 */
enum DocumentLanguage: string
{
    case French = 'fr';
    case English = 'en';

    public function label(): string
    {
        return match ($this) {
            self::French => 'Français',
            self::English => 'Anglais',
        };
    }
}
