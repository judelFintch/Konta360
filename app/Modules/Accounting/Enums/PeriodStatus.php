<?php

namespace App\Modules\Accounting\Enums;

enum PeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouverte',
            self::Closed => 'Clôturée',
        };
    }
}
