<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Party;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Companies\Enums\SequenceType;
use App\Modules\Companies\Validation\CompanyRule;
use App\Modules\Expenses\Enums\ExpenseStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Treasury\Enums\TreasuryTransactionType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $this->requireAny(Permission::AccountingView, Permission::AccountingEntriesCreate);
        $search = trim((string) $request->query('search'));
        $expenses = Expense::query()->with('supplier')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->latest('expense_date')->latest('id')->paginate(20)->withQueryString();

        return view('expenses.index', compact('expenses', 'search'));
    }

    public function create(): View
    {
        $this->require(Permission::AccountingEntriesCreate);

        return view('expenses.create', ['suppliers' => Party::query()
            ->whereIn('type', [PartyType::Supplier, PartyType::Both])
            ->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->require(Permission::AccountingEntriesCreate);
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', CompanyRule::exists('parties')],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:expense_date'],
            'description' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'in:CDF,USD'],
            'subtotal' => ['required', 'numeric', 'min:0.01'],
            'tax_total' => ['required', 'numeric', 'min:0'],
        ]);
        $subtotal = round((float) $data['subtotal'], 2);
        $tax = round((float) $data['tax_total'], 2);
        $expense = DB::transaction(function () use ($data, $subtotal, $tax) {
            $expense = Expense::create([
                ...$data, 'subtotal' => $subtotal, 'tax_total' => $tax, 'total' => $subtotal + $tax,
                'status' => ExpenseStatus::Draft, 'created_by' => auth()->id(),
            ]);
            $expense->update(['number' => SequenceType::Expense->nextNumber($expense->expense_date)]);

            return $expense;
        });

        return to_route('expenses.show', $expense)->with('success', 'La dépense brouillon a été créée.');
    }

    public function show(Expense $expense): View
    {
        $this->requireAny(Permission::AccountingView, Permission::AccountingEntriesCreate);
        $expense->load(['supplier', 'payments.treasuryAccount']);
        $accounts = TreasuryAccount::query()->where('is_active', true)
            ->where('currency', $expense->currency)->orderBy('name')->get();

        return view('expenses.show', compact('expense', 'accounts'));
    }

    public function validateExpense(Expense $expense, AccountingService $accounting): RedirectResponse
    {
        $this->require(Permission::AccountingEntriesPost);
        abort_unless($expense->status === ExpenseStatus::Draft, 409, 'Seule une dépense brouillon peut être validée.');

        DB::transaction(function () use ($expense, $accounting) {
            $expense->update(['status' => ExpenseStatus::Validated, 'validated_at' => now(), 'validated_by' => auth()->id()]);
            $accounting->postExpense($expense, auth()->id());
        });

        return back()->with('success', 'La dépense a été validée et comptabilisée.');
    }

    public function pay(Request $request, Expense $expense, AccountingService $accounting): RedirectResponse
    {
        $this->require(Permission::TreasuryManage);
        $data = $request->validate([
            'treasury_account_id' => ['required', 'integer', CompanyRule::exists('treasury_accounts')],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($expense, $data, $accounting) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            abort_unless($expense->status === ExpenseStatus::Validated, 409, 'Cette dépense ne peut pas recevoir de paiement.');
            $account = TreasuryAccount::query()->lockForUpdate()->findOrFail($data['treasury_account_id']);
            $amount = round((float) $data['amount'], 2);
            if (! $account->is_active || $account->currency !== $expense->currency) {
                throw ValidationException::withMessages(['treasury_account_id' => 'Compte de trésorerie incompatible.']);
            }
            if ($amount > $expense->balanceDue()) {
                throw ValidationException::withMessages(['amount' => 'Le paiement dépasse le solde de la dépense.']);
            }
            if ($amount > $account->balance()) {
                throw ValidationException::withMessages(['amount' => 'Le solde du compte de trésorerie est insuffisant.']);
            }

            $payment = ExpensePayment::create([
                ...$data, 'expense_id' => $expense->id, 'amount' => $amount,
                'currency' => $expense->currency, 'created_by' => auth()->id(),
            ]);
            $payment->update(['number' => SequenceType::ExpensePayment->nextNumber($payment->payment_date)]);
            $accounting->postExpensePayment($payment, auth()->id());
            $movement = TreasuryTransaction::create([
                'treasury_account_id' => $account->id, 'type' => TreasuryTransactionType::Outflow,
                'transaction_date' => $payment->payment_date, 'amount' => $amount, 'currency' => $expense->currency,
                'description' => "Paiement fournisseur {$payment->number} — {$expense->number}",
                'reference' => $payment->reference, 'source_type' => 'expense_payment',
                'source_id' => $payment->id, 'created_by' => auth()->id(),
            ]);
            $movement->update(['number' => SequenceType::TreasuryTransaction->nextNumber($movement->transaction_date)]);
            if ($expense->balanceDue() <= 0) {
                $expense->update(['status' => ExpenseStatus::Paid]);
            }
        });

        return back()->with('success', 'Le paiement fournisseur a été comptabilisé.');
    }

    private function require(Permission $permission): void
    {
        abort_unless(auth()->user()->can($permission->value), 403);
    }

    private function requireAny(Permission ...$permissions): void
    {
        abort_unless(collect($permissions)->contains(fn ($permission) => auth()->user()->can($permission->value)), 403);
    }
}
