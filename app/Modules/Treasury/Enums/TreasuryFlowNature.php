<?php

namespace App\Modules\Treasury\Enums;

use App\Models\TreasuryTransaction;

/**
 * Economic nature of a treasury movement, used to group receipts and
 * payments in the printed reports (direct-method cash flow presentation).
 */
enum TreasuryFlowNature: string
{
    case CustomerReceipt = 'customer_receipt';
    case OtherReceipt = 'other_receipt';
    case SupplierPayment = 'supplier_payment';
    case ReceiptReversal = 'receipt_reversal';
    case OtherPayment = 'other_payment';
    case InternalTransfer = 'internal_transfer';

    public static function of(TreasuryTransaction $transaction): self
    {
        if ($transaction->type === TreasuryTransactionType::Transfer) {
            return self::InternalTransfer;
        }

        return match ($transaction->source_type) {
            'payment' => self::CustomerReceipt,
            'expense_payment' => self::SupplierPayment,
            'payment_reversal' => self::ReceiptReversal,
            default => $transaction->type === TreasuryTransactionType::Inflow ? self::OtherReceipt : self::OtherPayment,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CustomerReceipt => 'Règlements clients',
            self::OtherReceipt => 'Autres encaissements',
            self::SupplierPayment => 'Paiements fournisseurs',
            self::ReceiptReversal => 'Annulations de règlements clients',
            self::OtherPayment => 'Autres décaissements',
            self::InternalTransfer => 'Virements internes',
        };
    }

    public function isReceipt(): bool
    {
        return in_array($this, [self::CustomerReceipt, self::OtherReceipt], true);
    }
}
