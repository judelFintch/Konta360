<?php

namespace App\Modules\Invoices\Services;

use App\Models\Invoice;
use App\Models\InvoiceDeduction;
use App\Models\InvoiceDeductionCorrection;
use App\Models\Payment;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Invoices\Enums\DeductionType;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Services\PaymentRecorder;
use App\Modules\Payments\Services\PaymentReverser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceDeductionCorrector
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly PaymentRecorder $recorder,
        private readonly PaymentReverser $reverser,
    ) {}

    public function version(Invoice $invoice): string
    {
        return hash('sha256', $invoice->deductions()->get()->toJson());
    }

    public function correct(Invoice $invoice, array $data, int $userId): InvoiceDeductionCorrection
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($invoice->status === InvoiceStatus::Validated, 409, 'Seule une facture validée peut être corrigée.');
            if (! hash_equals($this->version($invoice), $data['deduction_version'])) {
                $this->invalid('Les déductions ont changé. Rechargez la facture avant de corriger.');
            }
            $old = $invoice->deductions()->lockForUpdate()->get()->keyBy('id');
            $before = $old->values()->toArray();
            $oldOffset = round($old->filter(fn ($item) => ! $item->isAdvance())->sum('amount'), 2);
            $rows = [];
            foreach ($data['deductions'] ?? [] as $index => $row) {
                $id = $row['id'] ?? null;
                if ($id && ! $old->has($id)) {
                    $this->invalid('Une déduction ne fait pas partie de cette facture.');
                }
                $advance = $row['type'] === DeductionType::Advance->value;
                $amount = round((float) $row['quantity'] * (float) $row['unit_price'], 2);
                if ($amount <= 0 || $amount > (float) $invoice->total) {
                    $this->invalid('Le montant de chaque déduction doit être positif et ne pas dépasser le total TTC.');
                }
                // Model casts provide a stable comparison independent of form number formatting.
                $model = new InvoiceDeduction([
                    'position' => $index + 1,
                    'type' => $row['type'],
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'amount' => $amount,
                    'received_on' => $advance ? $row['received_on'] : null,
                    'payment_method' => $advance ? $row['payment_method'] : null,
                    'treasury_account_id' => $advance ? ($row['treasury_account_id'] ?? null) : null,
                    'reference' => $advance ? ($row['reference'] ?? null) : null,
                ]);
                $rows[] = ['id' => $id, 'model' => $model];
            }
            $total = round(collect($rows)->sum(fn ($row) => (float) $row['model']->amount), 2);
            if ($total > (float) $invoice->total) {
                $this->invalid('Les déductions dépassent le total TTC.');
            }
            $changed = count($rows) !== $old->count();
            $retainedPayments = [];
            foreach ($rows as $row) {
                $previous = $old->get($row['id']);
                $model = $row['model'];
                if (! $previous || $model->toArray() != array_intersect_key($previous->toArray(), $model->toArray())) {
                    $changed = true;
                }
                if ($previous?->isAdvance() && $model->isAdvance()
                    && $this->paymentDetails($previous) === $this->paymentDetails($model)) {
                    $payment = Payment::query()->lockForUpdate()->find($previous->payment_id);
                    if ($payment && $payment->invoice_id !== $invoice->id) {
                        abort(409, 'Le règlement ne correspond pas à cette facture.');
                    }
                    if ($payment?->status !== PaymentStatus::Recorded) {
                        $this->invalid('Une avance inchangée est liée à un règlement annulé ou absent. Retirez cette avance ou corrigez ses informations de paiement.');
                    }
                    $model->payment_id = $payment->id;
                    $retainedPayments[] = $payment->id;
                }
            }
            if (! $changed) {
                $this->invalid('Aucune déduction modifiée.');
            }
            // Reverse only advances whose financial details changed or which were removed.
            foreach ($old as $previous) {
                if ($previous->isAdvance() && $previous->payment_id && ! in_array($previous->payment_id, $retainedPayments, true)) {
                    $payment = Payment::query()->lockForUpdate()->findOrFail($previous->payment_id);
                    abort_unless($payment->invoice_id === $invoice->id, 409);
                    if ($payment->status === PaymentStatus::Recorded) {
                        $this->reverser->reverse($payment, trim($data['reason']), $userId);
                    }
                }
            }
            $newOffset = round(collect($rows)->sum(fn ($row) => $row['model']->isAdvance() ? 0 : (float) $row['model']->amount), 2);
            $newAdvances = round(collect($rows)->sum(fn ($row) => $row['model']->isAdvance() && ! $row['model']->payment_id ? (float) $row['model']->amount : 0), 2);
            if (round($invoice->paidAmount() + $invoice->creditedAmount() + $newOffset + $newAdvances, 2) > (float) $invoice->total) {
                $this->invalid('La correction dépasserait le total TTC avec les règlements et avoirs existants.');
            }
            $keptIds = [];
            foreach ($rows as $row) {
                $model = $row['model'];
                if ($model->isAdvance() && ! $model->payment_id) {
                    $payment = $this->recorder->record($invoice, [
                        'payment_date' => $model->received_on,
                        'amount' => $model->amount,
                        'method' => $model->payment_method,
                        'treasury_account_id' => $model->treasury_account_id,
                        'reference' => $model->reference,
                        'notes' => "Correction avance {$invoice->number} : ".trim($data['reason']),
                    ], $userId);
                    $model->payment_id = $payment->id;
                    // Keep the actual resolved account in the document as well.
                    $model->treasury_account_id = $payment->treasury_account_id;
                }
                if ($row['id']) {
                    $deduction = $old->get($row['id']);
                    $deduction->update([...$model->getAttributes(), 'payment_id' => $model->payment_id]);
                } else {
                    $deduction = $invoice->deductions()->create($model->getAttributes());
                }
                $keptIds[] = $deduction->id;
            }
            $invoice->deductions()->whereNotIn('id', $keptIds)->delete();
            $invoice->update(['deductions_total' => $total]);
            $correction = InvoiceDeductionCorrection::create([
                'invoice_id' => $invoice->id,
                'created_by' => $userId,
                'reason' => trim($data['reason']),
                'old_deductions' => $before,
                'new_deductions' => $invoice->deductions()->get()->toArray(),
            ]);
            $this->accounting->postInvoiceDeductionCorrection($invoice, $correction, round($newOffset - $oldOffset, 2), $userId);

            return $correction;
        });
    }

    private function paymentDetails(InvoiceDeduction $deduction): array
    {
        return [
            $deduction->amount,
            $deduction->received_on?->format('Y-m-d'),
            $deduction->payment_method?->value,
            $deduction->treasury_account_id ? (int) $deduction->treasury_account_id : null,
            $deduction->reference,
        ];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['deductions' => $message]);
    }
}
