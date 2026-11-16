<?php

namespace App\Modules\Payments\Services;

use App\Models\Payment;
use App\Models\TreasuryTransaction;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Companies\Enums\SequenceType;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Treasury\Enums\TreasuryTransactionType;

class PaymentReverser
{
    public function __construct(private readonly AccountingService $accounting) {}

    // Must run inside a database transaction.
    public function reverse(Payment $payment, string $reason, int $userId): void
    {
        $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
        abort_unless($payment->status === PaymentStatus::Recorded, 409, 'Ce règlement est déjà annulé.');
        $this->accounting->postPayment($payment, $userId);
        $payment->update([
            'status' => PaymentStatus::Reversed,
            'reversed_at' => now(),
            'reversed_by' => $userId,
            'reversal_reason' => $reason,
        ]);
        $this->accounting->reversePayment($payment, $userId);
        $originalMovement = TreasuryTransaction::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->first();
        if ($originalMovement) {
            $movement = TreasuryTransaction::create([
                'treasury_account_id' => $originalMovement->treasury_account_id,
                'type' => TreasuryTransactionType::Outflow,
                'transaction_date' => today(),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'description' => "Annulation du règlement {$payment->number}",
                'reference' => $payment->reversal_reason,
                'source_type' => 'payment_reversal',
                'source_id' => $payment->id,
                'created_by' => $userId,
            ]);
            $movement->update(['number' => SequenceType::TreasuryTransaction->nextNumber($movement->transaction_date)]);
        }
    }
}
