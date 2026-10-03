<?php

namespace App\Modules\Invoices\Enums;

enum DeductionType: string
{
    /** Money received before the invoice; recorded as a payment on validation. */
    case Advance = 'advance';

    /**
     * Costs the customer paid on our behalf (operator, fuel…), offset
     * against the receivable. They do not reduce the VAT base.
     */
    case ClientExpense = 'client_expense';

    public function label(): string
    {
        return match ($this) {
            self::Advance => 'Avance reçue',
            self::ClientExpense => 'Frais supportés par le client',
        };
    }
}
