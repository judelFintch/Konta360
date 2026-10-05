<?php

namespace App\Modules\Companies\Enums;

use App\Models\Company;
use App\Modules\Companies\Services\DocumentNumberer;
use DateTimeInterface;

/**
 * Every numbered document. Each one has its own sequence per company and
 * per year (ADR 0002 § 7).
 */
enum SequenceType: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Payment = 'payment';
    case AccountingEntry = 'accounting_entry';
    case TreasuryTransaction = 'treasury_transaction';
    case BankReconciliation = 'bank_reconciliation';
    case Expense = 'expense';
    case ExpensePayment = 'expense_payment';
    case FixedAsset = 'fixed_asset';

    /**
     * Next number of this sequence for the current company.
     */
    public function nextNumber(DateTimeInterface $date): string
    {
        return app(DocumentNumberer::class)->next($this, $date);
    }

    /**
     * Commercial documents use the prefixes configured by the company; the
     * other numbers keep their fixed internal format.
     */
    public function prefix(Company $company): string
    {
        return match ($this) {
            self::Quote => $company->quote_prefix,
            self::Invoice => $company->invoice_prefix,
            self::CreditNote => $company->credit_note_prefix,
            self::Payment => 'REG',
            self::AccountingEntry => 'ECR',
            self::TreasuryTransaction => 'TRES',
            self::BankReconciliation => 'RAP',
            self::Expense => 'DEP',
            self::ExpensePayment => 'PAI',
            self::FixedAsset => 'IMM',
        };
    }

    public function padding(Company $company): int
    {
        return match ($this) {
            self::Quote, self::Invoice, self::CreditNote => $company->number_padding,
            self::AccountingEntry => 6,
            default => 5,
        };
    }
}
