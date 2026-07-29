<?php

namespace App\Modules\Treasury\Enums;

enum BankReconciliationStatus: string
{
    case Completed = 'completed';

    public function label(): string
    {
        return 'Rapproché';
    }
}
