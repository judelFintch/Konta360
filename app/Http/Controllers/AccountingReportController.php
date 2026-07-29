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
