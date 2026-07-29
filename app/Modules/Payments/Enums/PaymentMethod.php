<?php

namespace App\Modules\Payments\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case MobileMoney = 'mobile_money';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Espèces',
            self::BankTransfer => 'Virement bancaire',
            self::Card => 'Carte',
            self::MobileMoney => 'Mobile Money',
            self::Cheque => 'Chèque',
            self::Other => 'Autre',
        };
    }
}
