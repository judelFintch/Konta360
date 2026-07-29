<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingEntryLine;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Administration\Enums\Permission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AccountingReportController extends Controller
{
    public function trialBalance(Request $request): View
    {
        $this->requirePermission(Permission::AccountingView);
        $filters = $this->filters($request);

        return view('accounting.reports.trial-balance', [
            ...$filters,
            'accounts' => $this->trialBalanceAccounts($filters),
        ]);
    }

    public function trialBalancePdf(Request $request): Response
    {
        $this->requirePermission(Permission::AccountingView);
        $this->requirePermission(Permission::ReportsExport);
        $filters = $this->filters($request);

        return Pdf::loadView('accounting.reports.trial-balance-pdf', [
            ...$filters,
            'accounts' => $this->trialBalanceAccounts($filters),
        ])->setPaper('a4', 'landscape')->download(
            "balance-{$filters['currency']}-{$filters['dateFrom']}-{$filters['dateTo']}.pdf"
        );
    }

    public function ledger(Request $request): View
    {
        $this->requirePermission(Permission::AccountingView);
        $filters = $this->filters($request);
        $accountId = $request->integer('account');
        $account = Account::query()->find($accountId);

        $lines = collect();
        if ($account) {
            $lines = AccountingEntryLine::query()
                ->with(['entry.journal'])
                ->where('account_id', $account->id)
                ->whereHas('entry', fn (Builder $query) => $this->constrainEntries($query, $filters))
                ->get()
                ->sortBy([
                    fn ($line) => $line->entry->entry_date->format('Y-m-d'),
                    fn ($line) => $line->entry->id,
                    fn ($line) => $line->position,
                ])
                ->values();
        }

        return view('accounting.reports.ledger', [
            ...$filters,
            'accounts' => Account::query()->where('is_active', true)->orderBy('code')->get(),
            'account' => $account,
            'lines' => $lines,
        ]);
    }

    public function incomeStatement(Request $request): View
    {
        $this->requirePermission(Permission::FinancialStatementsView);
        $filters = $this->filters($request);

        return view('accounting.reports.income-statement', [
            ...$filters,
            ...$this->incomeStatementData($filters),
        ]);
    }

    public function incomeStatementPdf(Request $request): Response
    {
        $this->requirePermission(Permission::FinancialStatementsView);
        $this->requirePermission(Permission::ReportsExport);
        $filters = $this->filters($request);

        return Pdf::loadView('accounting.reports.income-statement-pdf', [
            ...$filters,
            ...$this->incomeStatementData($filters),
        ])->setPaper('a4')->download(
            "compte-resultat-{$filters['currency']}-{$filters['dateFrom']}-{$filters['dateTo']}.pdf"
        );
    }

    public function balanceSheet(Request $request): View
    {
        $this->requirePermission(Permission::FinancialStatementsView);
        $filters = $this->filters($request);

        return view('accounting.reports.balance-sheet', [
            ...$filters,
            ...$this->balanceSheetData($filters),
        ]);
    }

    public function balanceSheetPdf(Request $request): Response
    {
        $this->requirePermission(Permission::FinancialStatementsView);
        $this->requirePermission(Permission::ReportsExport);
        $filters = $this->filters($request);

        return Pdf::loadView('accounting.reports.balance-sheet-pdf', [
            ...$filters,
            ...$this->balanceSheetData($filters),
        ])->setPaper('a4')->download(
            "bilan-{$filters['currency']}-{$filters['dateTo']}.pdf"
        );
    }

    /**
     * @return array{dateFrom: string, dateTo: string, currency: string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'currency' => ['nullable', 'in:CDF,USD'],
        ]);

        return [
            'dateFrom' => $validated['date_from'] ?? now()->startOfYear()->format('Y-m-d'),
            'dateTo' => $validated['date_to'] ?? now()->endOfYear()->format('Y-m-d'),
            'currency' => $validated['currency'] ?? 'CDF',
        ];
    }

    private function trialBalanceAccounts(array $filters)
    {
        return Account::query()
            ->withSum([
                'entryLines as total_debit' => fn (Builder $query) => $query
                    ->whereHas('entry', fn (Builder $query) => $this->constrainEntries($query, $filters)),
            ], 'debit')
            ->withSum([
                'entryLines as total_credit' => fn (Builder $query) => $query
                    ->whereHas('entry', fn (Builder $query) => $this->constrainEntries($query, $filters)),
            ], 'credit')
            ->orderBy('code')
            ->get()
            ->filter(fn (Account $account) => (float) $account->total_debit !== 0.0 || (float) $account->total_credit !== 0.0)
            ->values();
    }

    private function incomeStatementData(array $filters): array
    {
        $accounts = $this->accountsWithMovements($filters);
        $revenues = $accounts->where('type', 'revenue')->values();
        $expenses = $accounts->where('type', 'expense')->values();
        $totalRevenue = round($revenues->sum(fn ($account) => (float) $account->total_credit - (float) $account->total_debit), 2);
        $totalExpense = round($expenses->sum(fn ($account) => (float) $account->total_debit - (float) $account->total_credit), 2);

        return compact('revenues', 'expenses', 'totalRevenue', 'totalExpense') + [
            'netIncome' => round($totalRevenue - $totalExpense, 2),
        ];
    }

    private function balanceSheetData(array $filters): array
    {
        $cumulativeFilters = [...$filters, 'dateFrom' => '1900-01-01'];
        $accounts = $this->accountsWithMovements($cumulativeFilters);
        $assets = $accounts->whereIn('type', ['asset', 'receivable', 'contra_asset'])->values();
        $liabilities = $accounts->where('type', 'liability')->values();
        $equity = $accounts->where('type', 'equity')->values();
        $totalAssets = round($assets->sum(fn ($account) => (float) $account->total_debit - (float) $account->total_credit), 2);
        $totalLiabilities = round($liabilities->sum(fn ($account) => (float) $account->total_credit - (float) $account->total_debit), 2);
        $totalEquityAccounts = round($equity->sum(fn ($account) => (float) $account->total_credit - (float) $account->total_debit), 2);
        $income = $this->incomeStatementData($cumulativeFilters);
        $retainedIncome = $income['netIncome'];

        return compact(
            'assets',
            'liabilities',
            'equity',
            'totalAssets',
            'totalLiabilities',
            'totalEquityAccounts',
            'retainedIncome'
        ) + [
            'totalLiabilitiesAndEquity' => round($totalLiabilities + $totalEquityAccounts + $retainedIncome, 2),
        ];
    }

    private function accountsWithMovements(array $filters)
    {
        return Account::query()
            ->withSum([
                'entryLines as total_debit' => fn (Builder $query) => $query
                    ->whereHas('entry', fn (Builder $query) => $this->constrainEntries($query, $filters)),
            ], 'debit')
            ->withSum([
                'entryLines as total_credit' => fn (Builder $query) => $query
                    ->whereHas('entry', fn (Builder $query) => $this->constrainEntries($query, $filters)),
            ], 'credit')
            ->orderBy('code')
            ->get()
            ->filter(fn (Account $account) => (float) $account->total_debit !== 0.0 || (float) $account->total_credit !== 0.0)
            ->values();
    }

    private function constrainEntries(Builder $query, array $filters): Builder
    {
        return $query
            ->where('status', EntryStatus::Posted)
            ->where('currency', $filters['currency'])
            ->whereBetween('entry_date', [$filters['dateFrom'], $filters['dateTo']]);
    }

    private function requirePermission(Permission $permission): void
    {
        abort_unless(auth()->user()->can($permission->value), 403);
    }
}
