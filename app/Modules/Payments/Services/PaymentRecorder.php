<?php

namespace App\Modules\Payments\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Treasury\Enums\TreasuryTransactionType;
use Illuminate\Validation\ValidationException;

/**
 * Records a customer payment: the payment itself, its accounting entry and,
 * when treasury accounts are in use, the matching cash movement. Must be
 * called inside a database transaction.
 */
class PaymentRecorder
{
    public function __construct(private readonly AccountingService $accounting) {}

    /**
     * @param  array{payment_date: mixed, amount: float|string, method: mixed, treasury_account_id?: int|string|null, reference?: string|null, notes?: string|null}  $data
     */
    public function record(Invoice $invoice, array $data, int $userId): Payment
    {
        $treasuryAccount = $this->resolveTreasuryAccount($invoice, $data['treasury_account_id'] ?? null);

        $payment = Payment::create([
            ...$data,
            'invoice_id' => $invoice->id,
            'treasury_account_id' => $treasuryAccount?->id,
            'amount' => round((float) $data['amount'], 2),
            'currency' => $invoice->currency,
            'status' => PaymentStatus::Recorded,
            'recorded_by' => $userId,
        ]);
        $payment->update([
            'number' => sprintf('REG-%s-%05d', $payment->payment_date->format('Y'), $payment->id),
        ]);
        $this->accounting->postPayment($payment, $userId);

        if ($treasuryAccount) {
            $movement = TreasuryTransaction::create([
                'treasury_account_id' => $treasuryAccount->id,
                'type' => TreasuryTransactionType::Inflow,
                'transaction_date' => $payment->payment_date,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'description' => "Règlement {$payment->number} — {$invoice->number}",
                'reference' => $payment->reference,
                'source_type' => 'payment',
                'source_id' => $payment->id,
                'created_by' => $userId,
            ]);
            $movement->update(['number' => sprintf('TRES-%s-%05d', $movement->transaction_date->format('Y'), $movement->id)]);
        }

        return $payment;
    }

    /**
     * Once a treasury account exists in the invoice currency, every payment
     * must say which account received the money.
     */
    public function resolveTreasuryAccount(Invoice $invoice, int|string|null $treasuryAccountId): ?TreasuryAccount
    {
        if (! TreasuryAccount::query()->where('is_active', true)->where('currency', $invoice->currency)->exists()) {
            return null;
        }

        if (empty($treasuryAccountId)) {
            throw ValidationException::withMessages(['treasury_account_id' => 'Sélectionnez le compte qui reçoit le règlement.']);
        }

        $treasuryAccount = TreasuryAccount::query()->lockForUpdate()->findOrFail($treasuryAccountId);
        if (! $treasuryAccount->is_active || $treasuryAccount->currency !== $invoice->currency) {
            throw ValidationException::withMessages(['treasury_account_id' => 'Ce compte de trésorerie ne peut pas recevoir ce règlement.']);
        }

        return $treasuryAccount;
    }
}
