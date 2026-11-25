<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Company;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Companies\Enums\SequenceType;
use App\Modules\Companies\Validation\CompanyRule;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Treasury\Enums\TreasuryFlowNature;
use App\Modules\Treasury\Enums\TreasuryAccountType;
use App\Modules\Treasury\Enums\TreasuryTransactionType;
use App\Modules\Treasury\Services\TreasuryReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TreasuryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeTreasury();
        $accountId = $request->integer('account_id') ?: null;
        $type = $request->query('type');
        $accounts = TreasuryAccount::query()->orderByDesc('is_active')->orderBy('name')->get();
        $transactions = TreasuryTransaction::query()
            ->with(['account', 'destinationAccount', 'creator'])
            ->when($accountId, fn ($query) => $query->where(function ($query) use ($accountId) {
                $query->where('treasury_account_id', $accountId)->orWhere('destination_account_id', $accountId);
            }))
            ->when(
                in_array($type, array_column(TreasuryTransactionType::cases(), 'value'), true),
                fn ($query) => $query->where('type', $type)
            )
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
        $pendingPayments = Payment::query()
            ->with(['invoice.party'])
            ->where('status', PaymentStatus::Recorded)
            ->whereNull('treasury_account_id')
            ->oldest('payment_date')
            ->oldest('id')
            ->get();

        return view('treasury.index', compact('accounts', 'transactions', 'pendingPayments', 'accountId', 'type'));
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizeTreasury();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TreasuryAccountType::class)],
            'currency' => ['required', Rule::in(['CDF', 'USD'])],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        TreasuryAccount::create([...$data, 'is_active' => true, 'created_by' => auth()->id()]);

        return back()->with('success', 'Le compte de trésorerie a été créé.');
    }

    public function createTransaction(): View
    {
        $this->authorizeTreasury();
        $accounts = TreasuryAccount::query()->where('is_active', true)->orderBy('name')->get();
        abort_if($accounts->isEmpty(), 409, 'Créez d’abord un compte bancaire ou une caisse.');

        return view('treasury.create-transaction', compact('accounts'));
    }

    public function storeTransaction(Request $request, AccountingService $accounting): RedirectResponse
    {
        $this->authorizeTreasury();
        $data = $request->validate([
            'treasury_account_id' => ['required', 'integer', CompanyRule::exists('treasury_accounts')],
            'destination_account_id' => ['nullable', 'integer', CompanyRule::exists('treasury_accounts'), 'different:treasury_account_id'],
            'type' => ['required', Rule::enum(TreasuryTransactionType::class)],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $transaction = DB::transaction(function () use ($data, $accounting) {
            $account = TreasuryAccount::query()->lockForUpdate()->findOrFail($data['treasury_account_id']);
            abort_unless($account->is_active, 409, 'Ce compte de trésorerie est inactif.');
            $type = TreasuryTransactionType::from($data['type']);
            $destination = null;

            if ($type === TreasuryTransactionType::Transfer) {
                if (empty($data['destination_account_id'])) {
                    throw ValidationException::withMessages(['destination_account_id' => 'Le compte destinataire est obligatoire pour un virement.']);
                }
                $destination = TreasuryAccount::query()->lockForUpdate()->findOrFail($data['destination_account_id']);
                abort_unless($destination->is_active, 409, 'Le compte destinataire est inactif.');
                if ($destination->currency !== $account->currency) {
                    throw ValidationException::withMessages(['destination_account_id' => 'Les deux comptes doivent utiliser la même devise.']);
                }
            } elseif (! empty($data['destination_account_id'])) {
                $data['destination_account_id'] = null;
            }

            $amount = round((float) $data['amount'], 2);
            if ($type !== TreasuryTransactionType::Inflow && $amount > $account->balance()) {
                throw ValidationException::withMessages([
                    'amount' => 'Solde insuffisant. Disponible : '.number_format($account->balance(), 2, ',', ' ').' '.$account->currency.'.',
                ]);
            }

            $transaction = TreasuryTransaction::create([
                ...$data,
                'destination_account_id' => $destination?->id,
                'amount' => $amount,
                'currency' => $account->currency,
                'created_by' => auth()->id(),
            ]);
            $transaction->update([
                'number' => SequenceType::TreasuryTransaction->nextNumber($transaction->transaction_date),
            ]);
            $accounting->postTreasuryTransaction($transaction, auth()->id());

            return $transaction;
        });

        return to_route('treasury.transactions.proof', ['transaction' => $transaction, 'format' => 'epson']);
    }

    public function proof(TreasuryTransaction $transaction, TreasuryReportBuilder $reports, string $format = 'epson'): Response
    {
        $this->authorizeTreasury();
        abort_unless(in_array($format, ['epson', 'a4'], true), 404);
        $transaction->loadMissing(['account', 'destinationAccount', 'creator']);
        $company = Company::current();
        $qrData = route('treasury.transactions.proof', ['transaction' => $transaction, 'format' => 'a4']);
        $thermalText = implode(' ', array_filter([
            $company->name,
            $company->address,
            $company->phone,
            $company->email,
            $transaction->account->name,
            $transaction->destinationAccount?->name,
            $transaction->reference,
            $transaction->description,
        ]));
        $statement = $reports->statement(
            $transaction->account,
            $transaction->transaction_date->toDateString(),
            $transaction->transaction_date->toDateString(),
        );
        $line = $statement['lines']->firstWhere('transaction.id', $transaction->id);
        $balanceAfter = $line['balance'] ?? null;
        $heightMm = 205 + max(0, (int) ceil(mb_strlen($thermalText) / 32) - 8) * 4;

        $pdf = Pdf::loadView('treasury.proof', [
            'company' => $company,
            'transaction' => $transaction,
            'format' => $format,
            'nature' => TreasuryFlowNature::of($transaction),
            'balanceAfter' => $balanceAfter,
            'printedAt' => now(),
            'qrCode' => $this->proofQrCode($qrData),
            'money' => fn ($value) => number_format((float) $value, 2, ',', ' '),
        ]);

        if ($format === 'epson') {
            $pdf->setPaper([0, 0, 80 * 72 / 25.4, $heightMm * 72 / 25.4]);
        } else {
            $pdf->setPaper('a4', 'portrait');
        }

        return $pdf->stream('preuve-'.$transaction->number.'-'.$format.'.pdf');
    }

    public function assignPayment(Request $request, Payment $payment, AccountingService $accounting): RedirectResponse
    {
        $this->authorizeTreasury();
        $data = $request->validate([
            'treasury_account_id' => ['required', 'integer', CompanyRule::exists('treasury_accounts')],
        ]);

        $movement = DB::transaction(function () use ($payment, $data, $accounting) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($payment->status === PaymentStatus::Recorded, 409, 'Seul un règlement actif peut être affecté.');
            abort_if($payment->treasury_account_id, 409, 'Ce règlement est déjà affecté à la trésorerie.');
            $account = TreasuryAccount::query()->lockForUpdate()->findOrFail($data['treasury_account_id']);

            if (! $account->is_active || $account->currency !== $payment->currency) {
                throw ValidationException::withMessages([
                    'treasury_account_id' => 'Sélectionnez un compte actif dans la même devise que le règlement.',
                ]);
            }

            $payment->update(['treasury_account_id' => $account->id]);
            $payment->unsetRelation('treasuryAccount');
            $accounting->reclassifyPaymentCash($payment, auth()->id());
            $movement = TreasuryTransaction::create([
                'treasury_account_id' => $account->id,
                'type' => TreasuryTransactionType::Inflow,
                'transaction_date' => $payment->payment_date,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'description' => "Règlement historique {$payment->number} — {$payment->invoice()->value('number')}",
                'reference' => $payment->reference,
                'source_type' => 'payment',
                'source_id' => $payment->id,
                'created_by' => auth()->id(),
            ]);
            $movement->update(['number' => SequenceType::TreasuryTransaction->nextNumber($movement->transaction_date)]);

            return $movement;
        });

        return to_route('treasury.transactions.proof', ['transaction' => $movement, 'format' => 'epson']);
    }

    private function proofQrCode(string $data): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::M,
            'scale' => 5,
            'quietzoneSize' => 2,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($data);
    }

    private function authorizeTreasury(): void
    {
        abort_unless(auth()->user()->can(Permission::TreasuryManage->value), 403);
    }
}
