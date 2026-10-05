<?php

namespace App\Modules\Accounting\Services;

use App\Models\Account;
use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\FixedAssetDepreciation;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\TreasuryTransaction;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\PeriodStatus;
use App\Modules\Companies\Enums\SequenceType;
use App\Modules\Invoices\Enums\DeductionType;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Treasury\Enums\TreasuryTransactionType;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AccountingService
{
    public function postInvoice(Invoice $invoice, int $userId): AccountingEntry
    {
        $netSales = round((float) $invoice->subtotal - (float) $invoice->discount_total, 2);
        $lines = [
            $this->line('411', "Créance {$invoice->number}", (float) $invoice->total, 0),
            $this->line('70', "Vente {$invoice->number}", 0, $netSales),
        ];

        if ((float) $invoice->tax_total > 0) {
            $lines[] = $this->line('4431', "Taxes {$invoice->number}", 0, (float) $invoice->tax_total);
        }

        // Costs the customer paid on our behalf are offset against the
        // receivable in the same entry, so cancelling the invoice reverses
        // them too. Provisional account pending the chart of accounts.
        $offsets = $invoice->deductions()->where('type', DeductionType::ClientExpense)->get();
        foreach ($offsets as $offset) {
            $lines[] = $this->line('60', "{$offset->description} — {$invoice->number}", (float) $offset->amount, 0);
            $lines[] = $this->line('411', "Compensation {$invoice->number}", 0, (float) $offset->amount);
        }

        return $this->post(
            'invoice',
            $invoice->id,
            'VE',
            $invoice->issue_date->format('Y-m-d'),
            "Facture {$invoice->number} — {$invoice->party()->value('name')}",
            $invoice->currency,
            $lines,
            $userId
        );
    }

    public function postExpense(Expense $expense, int $userId): AccountingEntry
    {
        $lines = [
            $this->line('60', "Charge {$expense->number}", (float) $expense->subtotal, 0),
        ];
        if ((float) $expense->tax_total > 0) {
            $lines[] = $this->line('445', "Taxe déductible {$expense->number}", (float) $expense->tax_total, 0);
        }
        $lines[] = $this->line('401', "Dette fournisseur {$expense->number}", 0, (float) $expense->total);

        return $this->post(
            'expense',
            $expense->id,
            'AC',
            $expense->expense_date->format('Y-m-d'),
            "Dépense {$expense->number} — {$expense->description}",
            $expense->currency,
            $lines,
            $userId
        );
    }

    public function postExpensePayment(ExpensePayment $payment, int $userId): AccountingEntry
    {
        $payment->loadMissing('treasuryAccount');
        $cashCode = $payment->treasuryAccount->type->accountingCode();
        $journal = $payment->treasuryAccount->type->value === 'cash' ? 'CA' : 'BQ';

        return $this->post(
            'expense_payment',
            $payment->id,
            $journal,
            $payment->payment_date->format('Y-m-d'),
            "Paiement fournisseur {$payment->number}",
            $payment->currency,
            [
                $this->line('401', "Dette réglée {$payment->number}", (float) $payment->amount, 0),
                $this->line($cashCode, "Décaissement {$payment->number}", 0, (float) $payment->amount),
            ],
            $userId
        );
    }

    public function postPayment(Payment $payment, int $userId): AccountingEntry
    {
        $isCash = $payment->method === PaymentMethod::Cash;
        $cashAccount = $isCash ? '571' : '512';
        $journal = $isCash ? 'CA' : 'BQ';

        return $this->post(
            'payment',
            $payment->id,
            $journal,
            $payment->payment_date->format('Y-m-d'),
            "Règlement {$payment->number} — {$payment->invoice()->value('number')}",
            $payment->currency,
            [
                $this->line($cashAccount, "Encaissement {$payment->number}", (float) $payment->amount, 0),
                $this->line('411', "Règlement client {$payment->number}", 0, (float) $payment->amount),
            ],
            $userId
        );
    }

    public function postCreditNote(CreditNote $creditNote, int $userId): AccountingEntry
    {
        $netSales = round((float) $creditNote->subtotal - (float) $creditNote->discount_total, 2);
        $lines = [
            $this->line('70', "Retour sur vente {$creditNote->number}", $netSales, 0),
        ];

        if ((float) $creditNote->tax_total > 0) {
            $lines[] = $this->line('4431', "Taxes annulées {$creditNote->number}", (float) $creditNote->tax_total, 0);
        }

        $lines[] = $this->line('411', "Avoir client {$creditNote->number}", 0, (float) $creditNote->total);

        return $this->post(
            'credit_note',
            $creditNote->id,
            'VE',
            $creditNote->issue_date->format('Y-m-d'),
            "Avoir {$creditNote->number} — {$creditNote->party()->value('name')}",
            $creditNote->currency,
            $lines,
            $userId
        );
    }

    public function reversePayment(Payment $payment, int $userId): AccountingEntry
    {
        $original = AccountingEntry::query()
            ->with(['lines.account', 'journal'])
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->firstOrFail();

        $lines = $original->lines->map(fn ($line) => [
            'account_code' => $line->account->code,
            'description' => "Contre-passation {$payment->number}",
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
        ])->all();

        return $this->post(
            'payment_reversal',
            $payment->id,
            $original->journal->code,
            now()->format('Y-m-d'),
            "Annulation du règlement {$payment->number}",
            $payment->currency,
            $lines,
            $userId
        );
    }

    /**
     * Mirror entry of a cancelled invoice's sales entry, dated on the day of
     * the cancellation so that closed periods stay untouched.
     */
    public function reverseInvoice(Invoice $invoice, int $userId): ?AccountingEntry
    {
        $original = AccountingEntry::query()
            ->with(['lines.account', 'journal'])
            ->where('source_type', 'invoice')
            ->where('source_id', $invoice->id)
            ->first();

        if (! $original) {
            return null;
        }

        $lines = $original->lines->map(fn ($line) => [
            'account_code' => $line->account->code,
            'description' => "Contre-passation {$invoice->number}",
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
        ])->all();

        return $this->post(
            'invoice_reversal',
            $invoice->id,
            $original->journal->code,
            now()->format('Y-m-d'),
            "Annulation de la facture {$invoice->number}",
            $invoice->currency,
            $lines,
            $userId
        );
    }

    public function postDepreciation(FixedAssetDepreciation $depreciation, int $userId): AccountingEntry
    {
        $depreciation->loadMissing('asset');

        return $this->post(
            'fixed_asset_depreciation',
            $depreciation->id,
            'OD',
            $depreciation->period_date->format('Y-m-d'),
            "Dotation {$depreciation->asset->code} — {$depreciation->asset->name}",
            $depreciation->asset->currency,
            [
                $this->line('68', "Dotation {$depreciation->asset->code}", (float) $depreciation->amount, 0),
                $this->line('28', "Amortissement cumulé {$depreciation->asset->code}", 0, (float) $depreciation->amount),
            ],
            $userId
        );
    }

    public function postTreasuryTransaction(TreasuryTransaction $transaction, int $userId): AccountingEntry
    {
        $transaction->loadMissing(['account', 'destinationAccount']);
        $sourceCode = $transaction->account->type->accountingCode();
        $journalCode = $transaction->account->type->value === 'cash' ? 'CA' : 'BQ';

        $lines = match ($transaction->type) {
            TreasuryTransactionType::Inflow => [
                $this->line($sourceCode, $transaction->description, (float) $transaction->amount, 0),
                $this->line('75', $transaction->description, 0, (float) $transaction->amount),
            ],
            TreasuryTransactionType::Outflow => [
                $this->line('65', $transaction->description, (float) $transaction->amount, 0),
                $this->line($sourceCode, $transaction->description, 0, (float) $transaction->amount),
            ],
            TreasuryTransactionType::Transfer => [
                $this->line($transaction->destinationAccount->type->accountingCode(), "Réception {$transaction->number}", (float) $transaction->amount, 0),
                $this->line($sourceCode, "Envoi {$transaction->number}", 0, (float) $transaction->amount),
            ],
        };

        return $this->post(
            'treasury_transaction',
            $transaction->id,
            $journalCode,
            $transaction->transaction_date->format('Y-m-d'),
            "{$transaction->type->label()} {$transaction->number} — {$transaction->description}",
            $transaction->currency,
            $lines,
            $userId
        );
    }

    public function postManualEntry(AccountingEntry $entry, int $userId): AccountingEntry
    {
        $entry = AccountingEntry::query()->with('lines')->lockForUpdate()->findOrFail($entry->id);
        if ($entry->status === EntryStatus::Posted) {
            return $entry;
        }

        $this->ensurePeriodIsOpen($entry->entry_date->format('Y-m-d'));
        $debit = round((float) $entry->lines->sum('debit'), 2);
        $credit = round((float) $entry->lines->sum('credit'), 2);
        if ($debit <= 0 || abs($debit - $credit) > 0.001) {
            throw new LogicException("Écriture déséquilibrée : débit {$debit}, crédit {$credit}.");
        }

        $entry->update([
            'status' => EntryStatus::Posted,
            'posted_at' => now(),
            'posted_by' => $userId,
        ]);

        return $entry;
    }

    /**
     * @param  list<array{account_code: string, description: string, debit: float, credit: float}>  $lines
     */
    private function post(
        string $sourceType,
        int $sourceId,
        string $journalCode,
        string $date,
        string $label,
        string $currency,
        array $lines,
        int $userId
    ): AccountingEntry {
        $existing = AccountingEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $this->ensurePeriodIsOpen($date);

        $debit = round((float) collect($lines)->sum('debit'), 2);
        $credit = round((float) collect($lines)->sum('credit'), 2);

        if ($debit <= 0 || abs($debit - $credit) > 0.001) {
            throw new LogicException("Écriture déséquilibrée : débit {$debit}, crédit {$credit}.");
        }

        $entry = AccountingEntry::create([
            'journal_id' => Journal::where('code', $journalCode)->valueOrFail('id'),
            'entry_date' => $date,
            'label' => $label,
            'currency' => $currency,
            'status' => EntryStatus::Posted,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_by' => $userId,
            'posted_at' => now(),
            'posted_by' => $userId,
        ]);

        $entry->update(['number' => SequenceType::AccountingEntry->nextNumber($entry->entry_date)]);
        $entry->lines()->createMany(collect($lines)->values()->map(fn (array $line, int $index) => [
            'position' => $index + 1,
            'account_id' => Account::where('code', $line['account_code'])->valueOrFail('id'),
            'description' => $line['description'],
            'debit' => $line['debit'],
            'credit' => $line['credit'],
        ])->all());

        return $entry;
    }

    /**
     * @return array{account_code: string, description: string, debit: float, credit: float}
     */
    private function line(string $accountCode, string $description, float $debit, float $credit): array
    {
        return [
            'account_code' => $accountCode,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    private function ensurePeriodIsOpen(string $date): void
    {
        $closedPeriod = AccountingPeriod::query()
            ->where('status', PeriodStatus::Closed)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();

        if ($closedPeriod) {
            throw new ConflictHttpException("La période {$closedPeriod->name} est clôturée : aucune nouvelle écriture n’est autorisée.");
        }
    }
}
