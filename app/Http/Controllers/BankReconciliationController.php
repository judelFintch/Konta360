<?php

namespace App\Http\Controllers;

use App\Models\BankReconciliation;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Treasury\Enums\BankReconciliationStatus;
use App\Modules\Treasury\Enums\TreasuryAccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BankReconciliationController extends Controller
{
    public function index(): View
    {
        $this->authorizeTreasury();
        $reconciliations = BankReconciliation::query()
            ->with(['account', 'creator'])
            ->withCount('transactions')
            ->latest('ends_on')
            ->latest('id')
            ->paginate(20);

        return view('treasury.reconciliations.index', compact('reconciliations'));
    }

    public function create(Request $request): View
    {
        $this->authorizeTreasury();
        $accounts = TreasuryAccount::query()
            ->where('type', TreasuryAccountType::Bank)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $account = $accounts->firstWhere('id', $request->integer('account_id'));
        $startsOn = $request->date('starts_on')?->format('Y-m-d') ?? today()->startOfMonth()->format('Y-m-d');
        $endsOn = $request->date('ends_on')?->format('Y-m-d') ?? today()->format('Y-m-d');
        $transactions = collect();

        if ($account) {
            $transactions = TreasuryTransaction::query()
                ->whereBetween('transaction_date', [$startsOn, $endsOn])
                ->where(function ($query) use ($account) {
                    $query->where('treasury_account_id', $account->id)
                        ->orWhere('destination_account_id', $account->id);
                })
                ->whereDoesntHave('reconciliations', fn ($query) => $query->where('treasury_account_id', $account->id))
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();
        }

        return view('treasury.reconciliations.create', compact(
            'accounts', 'account', 'startsOn', 'endsOn', 'transactions'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeTreasury();
        $data = $request->validate([
            'treasury_account_id' => ['required', 'integer', 'exists:treasury_accounts,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'statement_opening_balance' => ['required', 'numeric'],
            'statement_closing_balance' => ['required', 'numeric'],
            'transactions' => ['nullable', 'array'],
            'transactions.*' => ['integer', 'exists:treasury_transactions,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $reconciliation = DB::transaction(function () use ($data) {
            $account = TreasuryAccount::query()->lockForUpdate()->findOrFail($data['treasury_account_id']);
            abort_unless($account->type === TreasuryAccountType::Bank, 409, 'Le rapprochement est réservé aux comptes bancaires.');
            $transactionIds = array_values(array_unique($data['transactions'] ?? []));
            $transactions = TreasuryTransaction::query()
                ->whereIn('id', $transactionIds)
                ->whereBetween('transaction_date', [$data['starts_on'], $data['ends_on']])
                ->where(function ($query) use ($account) {
                    $query->where('treasury_account_id', $account->id)
                        ->orWhere('destination_account_id', $account->id);
                })
                ->whereDoesntHave('reconciliations', fn ($query) => $query->where('treasury_account_id', $account->id))
                ->lockForUpdate()
                ->get();

            if ($transactions->count() !== count($transactionIds)) {
                throw ValidationException::withMessages([
                    'transactions' => 'Un ou plusieurs mouvements ne sont pas disponibles pour ce rapprochement.',
                ]);
            }

            $calculated = round((float) $data['statement_opening_balance']
                + $transactions->sum(fn (TreasuryTransaction $transaction) => $transaction->signedAmountFor($account)), 2);
            $difference = round((float) $data['statement_closing_balance'] - $calculated, 2);
            if (abs($difference) > 0.001) {
                throw ValidationException::withMessages([
                    'transactions' => 'Le rapprochement présente encore un écart de '
                        .number_format($difference, 2, ',', ' ').' '.$account->currency.'.',
                ]);
            }

            $reconciliation = BankReconciliation::create([
                'treasury_account_id' => $account->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'statement_opening_balance' => $data['statement_opening_balance'],
                'statement_closing_balance' => $data['statement_closing_balance'],
                'calculated_closing_balance' => $calculated,
                'difference' => $difference,
                'currency' => $account->currency,
                'status' => BankReconciliationStatus::Completed,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'completed_at' => now(),
                'completed_by' => auth()->id(),
            ]);
            $reconciliation->update([
                'number' => sprintf('RAP-%s-%05d', $reconciliation->ends_on->format('Y'), $reconciliation->id),
            ]);
            $reconciliation->transactions()->attach($transactions->modelKeys());

            return $reconciliation;
        });

        return to_route('treasury.reconciliations.show', $reconciliation)
            ->with('success', 'Le rapprochement bancaire a été validé sans écart.');
    }

    public function show(BankReconciliation $reconciliation): View
    {
        $this->authorizeTreasury();
        $reconciliation->load(['account', 'transactions', 'creator']);

        return view('treasury.reconciliations.show', compact('reconciliation'));
    }

    private function authorizeTreasury(): void
    {
        abort_unless(auth()->user()->can(Permission::TreasuryManage->value), 403);
    }
}
