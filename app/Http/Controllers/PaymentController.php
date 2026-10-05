<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Companies\Enums\SequenceType;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Services\PaymentRecorder;
use App\Modules\Treasury\Enums\TreasuryTransactionType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $this->requireAnyPermission(Permission::PaymentsRecord, Permission::PaymentsReverse);
        $search = trim((string) $request->query('search'));

        $payments = Payment::query()
            ->with(['invoice.party', 'recorder'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($query) => $query->where('number', 'like', "%{$search}%"))
                    ->orWhereHas('invoice.party', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('payments.index', compact('payments', 'search'));
    }

    public function create(Invoice $invoice): View
    {
        $this->requirePermission(Permission::PaymentsRecord);
        $invoice->load('party');
        $this->ensurePayable($invoice);
        $treasuryAccounts = TreasuryAccount::query()
            ->where('is_active', true)
            ->where('currency', $invoice->currency)
            ->orderBy('name')
            ->get();

        return view('payments.create', compact('invoice', 'treasuryAccounts'));
    }

    public function store(PaymentRequest $request, Invoice $invoice, PaymentRecorder $recorder): RedirectResponse
    {
        $this->requirePermission(Permission::PaymentsRecord);
        $data = $request->validated();

        DB::transaction(function () use ($invoice, $data, $recorder) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->ensurePayable($invoice);
            $balance = $invoice->balanceDue();

            if (round((float) $data['amount'], 2) > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Le montant ne peut pas dépasser le solde de '.number_format($balance, 2, ',', ' ').' '.$invoice->currency.'.',
                ]);
            }

            $recorder->record($invoice, $data, auth()->id());
        });

        return to_route('invoices.show', $invoice)->with('success', 'Le règlement a été enregistré.');
    }

    public function reverse(Request $request, Payment $payment, AccountingService $accounting): RedirectResponse
    {
        $this->requirePermission(Permission::PaymentsReverse);
        $data = $request->validate([
            'reversal_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        DB::transaction(function () use ($payment, $data, $accounting) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($payment->status === PaymentStatus::Recorded, 409, 'Ce règlement est déjà annulé.');
            $accounting->postPayment($payment, auth()->id());
            $payment->update([
                'status' => PaymentStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => trim($data['reversal_reason']),
            ]);
            $accounting->reversePayment($payment, auth()->id());
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
                    'created_by' => auth()->id(),
                ]);
                $movement->update(['number' => SequenceType::TreasuryTransaction->nextNumber($movement->transaction_date)]);
            }
        });

        return back()->with('success', 'Le règlement a été annulé et reste visible dans l’historique.');
    }

    private function ensurePayable(Invoice $invoice): void
    {
        abort_unless($invoice->status === InvoiceStatus::Validated, 409, 'Seule une facture validée peut recevoir un règlement.');
        abort_if($invoice->balanceDue() <= 0, 409, 'Cette facture est déjà entièrement payée.');
    }

    private function requirePermission(Permission $permission): void
    {
        abort_unless(auth()->user()->can($permission->value), 403);
    }

    private function requireAnyPermission(Permission ...$permissions): void
    {
        abort_unless(
            collect($permissions)->contains(fn (Permission $permission) => auth()->user()->can($permission->value)),
            403
        );
    }
}
