<?php

namespace App\Modules\Accounting\Services;

use App\Models\Account;
use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Payment;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\PeriodStatus;
use App\Modules\Payments\Enums\PaymentMethod;
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

        $entry->update(['number' => sprintf('ECR-%s-%06d', $entry->entry_date->format('Y'), $entry->id)]);
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
