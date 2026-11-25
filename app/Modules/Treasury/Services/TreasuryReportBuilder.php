<?php

namespace App\Modules\Treasury\Services;

use App\Models\BankReconciliation;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Modules\Treasury\Enums\TreasuryAccountType;
use App\Modules\Treasury\Enums\TreasuryFlowNature;
use App\Modules\Treasury\Enums\TreasuryTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Figures behind the printed treasury reports. Balances always follow the
 * same rule as TreasuryAccount::balance(): opening balance plus inflows and
 * incoming transfers, minus outflows and outgoing transfers.
 */
class TreasuryReportBuilder
{
    /**
     * Denominations printed on the cash count sheet (billetage).
     */
    public const DENOMINATIONS = [
        'CDF' => [20000, 10000, 5000, 1000, 500, 200, 100, 50, 20, 10],
        'USD' => [100, 50, 20, 10, 5, 2, 1],
    ];

    /**
     * Balance at the start of $date (movements of that day excluded).
     */
    public function balanceBefore(TreasuryAccount $account, string $date): float
    {
        return $this->balanceWhere($account, fn (Builder $query) => $query->whereDate('transaction_date', '<', $date));
    }

    /**
     * Balance at the end of $date (movements of that day included).
     */
    public function balanceAt(TreasuryAccount $account, string $date): float
    {
        return $this->balanceWhere($account, fn (Builder $query) => $query->whereDate('transaction_date', '<=', $date));
    }

    /**
     * Journal de caisse / de banque: every movement of the period with its
     * running balance, starting from the balance brought forward.
     *
     * @return array{account: TreasuryAccount, opening: float, lines: Collection, receipts: float, payments: float, closing: float}
     */
    public function statement(TreasuryAccount $account, string $from, string $to): array
    {
        $opening = $this->balanceBefore($account, $from);
        $balance = $opening;

        $lines = $this->periodMovements($account, $from, $to)
            ->with(['account', 'destinationAccount', 'reconciliations'])
            ->get()
            ->map(function (TreasuryTransaction $transaction) use ($account, &$balance) {
                $signed = $transaction->signedAmountFor($account);
                $balance = round($balance + $signed, 2);
                $counterpart = match (true) {
                    $transaction->type !== TreasuryTransactionType::Transfer => null,
                    $transaction->treasury_account_id === $account->id => '→ '.$transaction->destinationAccount?->name,
                    default => '← '.$transaction->account?->name,
                };

                return [
                    'transaction' => $transaction,
                    'nature' => TreasuryFlowNature::of($transaction),
                    'counterpart' => $counterpart,
                    'receipt' => $signed > 0 ? $signed : 0.0,
                    'payment' => $signed < 0 ? -$signed : 0.0,
                    'balance' => $balance,
                    'reconciliation' => $transaction->reconciliations
                        ->firstWhere('treasury_account_id', $account->id)?->number,
                ];
            });

        return [
            'account' => $account,
            'opening' => $opening,
            'lines' => $lines,
            'receipts' => round($lines->sum('receipt'), 2),
            'payments' => round($lines->sum('payment'), 2),
            'closing' => $balance,
        ];
    }

    /**
     * Situation de trésorerie: one row per account, grouped by currency
     * (amounts in different currencies are never added together).
     *
     * @return Collection<string, array{rows: Collection, subtotals: Collection, total: array}>
     */
    public function position(string $from, string $to): Collection
    {
        return TreasuryAccount::query()
            ->orderBy('currency')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function (TreasuryAccount $account) use ($from, $to) {
                $opening = $this->balanceBefore($account, $from);
                $movements = $this->periodMovements($account, $from, $to)->get();
                $receipts = round($movements->sum(fn ($t) => max(0, $t->signedAmountFor($account))), 2);
                $payments = round($movements->sum(fn ($t) => max(0, -$t->signedAmountFor($account))), 2);
                $closing = round($opening + $receipts - $payments, 2);

                return [
                    'account' => $account,
                    'opening' => $opening,
                    'receipts' => $receipts,
                    'payments' => $payments,
                    'closing' => $closing,
                    'count' => $movements->count(),
                    'observation' => match (true) {
                        $closing < 0 && $account->type === TreasuryAccountType::Cash => 'Caisse créditrice : anomalie à justifier',
                        $closing < 0 => 'Découvert bancaire',
                        ! $account->is_active => 'Compte inactif',
                        default => null,
                    },
                ];
            })
            ->filter(fn (array $row) => $row['account']->is_active || $row['count'] > 0 || abs($row['closing']) > 0.001)
            ->groupBy(fn (array $row) => $row['account']->currency)
            ->map(fn (Collection $rows) => [
                'rows' => $rows->values(),
                'subtotals' => $rows->groupBy(fn (array $row) => $row['account']->type->value)
                    ->map(fn (Collection $group) => $this->totals($group) + ['type' => $group->first()['account']->type]),
                'total' => $this->totals($rows),
            ]);
    }

    /**
     * Tableau des encaissements et décaissements (direct method) for one
     * currency: opening cash, receipts and payments by nature, closing cash,
     * then the month-by-month evolution. Internal transfers move money
     * between the company's own accounts and leave total cash unchanged.
     */
    public function flows(string $from, string $to, string $currency): array
    {
        $accounts = TreasuryAccount::query()->where('currency', $currency)->get();
        $opening = round($accounts->sum(fn (TreasuryAccount $account) => $this->balanceBefore($account, $from)), 2);
        $closingControl = round($accounts->sum(fn (TreasuryAccount $account) => $this->balanceAt($account, $to)), 2);

        $transactions = TreasuryTransaction::query()
            ->where('currency', $currency)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $receipts = collect(TreasuryFlowNature::cases())->filter->isReceipt()
            ->mapWithKeys(fn (TreasuryFlowNature $nature) => [$nature->value => ['nature' => $nature, 'amount' => 0.0, 'count' => 0]]);
        $payments = collect(TreasuryFlowNature::cases())
            ->reject(fn (TreasuryFlowNature $nature) => $nature->isReceipt() || $nature === TreasuryFlowNature::InternalTransfer)
            ->mapWithKeys(fn (TreasuryFlowNature $nature) => [$nature->value => ['nature' => $nature, 'amount' => 0.0, 'count' => 0]]);
        $transfers = ['amount' => 0.0, 'count' => 0];
        $months = [];

        foreach ($transactions as $transaction) {
            $nature = TreasuryFlowNature::of($transaction);
            $amount = (float) $transaction->amount;
            $month = $transaction->transaction_date->format('Y-m');
            $months[$month] ??= ['month' => $transaction->transaction_date->copy()->startOfMonth(), 'receipts' => 0.0, 'payments' => 0.0];

            if ($nature === TreasuryFlowNature::InternalTransfer) {
                $transfers['amount'] += $amount;
                $transfers['count']++;
            } elseif ($nature->isReceipt()) {
                $receipts->put($nature->value, ['nature' => $nature, 'amount' => $receipts[$nature->value]['amount'] + $amount, 'count' => $receipts[$nature->value]['count'] + 1]);
                $months[$month]['receipts'] += $amount;
            } else {
                $payments->put($nature->value, ['nature' => $nature, 'amount' => $payments[$nature->value]['amount'] + $amount, 'count' => $payments[$nature->value]['count'] + 1]);
                $months[$month]['payments'] += $amount;
            }
        }

        $totalReceipts = round($receipts->sum('amount'), 2);
        $totalPayments = round($payments->sum('amount'), 2);
        $running = $opening;
        $monthly = collect($months)->sortKeys()->values()->map(function (array $month) use (&$running) {
            $net = round($month['receipts'] - $month['payments'], 2);
            $running = round($running + $net, 2);

            return [...$month, 'net' => $net, 'closing' => $running];
        });

        return [
            'currency' => $currency,
            'accountsCount' => $accounts->count(),
            'opening' => $opening,
            'receipts' => $receipts->values(),
            'payments' => $payments->values(),
            'totalReceipts' => $totalReceipts,
            'totalPayments' => $totalPayments,
            'net' => round($totalReceipts - $totalPayments, 2),
            'closing' => round($opening + $totalReceipts - $totalPayments, 2),
            'closingControl' => $closingControl,
            'transfers' => $transfers,
            'monthly' => $monthly,
        ];
    }

    /**
     * Procès-verbal d'arrêté de caisse: book balance to compare with the
     * physical count.
     */
    public function cashCount(TreasuryAccount $account, string $date): array
    {
        $last = TreasuryTransaction::query()
            ->forAccount($account)
            ->whereDate('transaction_date', '<=', $date)
            ->latest('transaction_date')
            ->latest('id')
            ->first();

        return [
            'account' => $account,
            'date' => Carbon::parse($date),
            'bookBalance' => $this->balanceAt($account, $date),
            'lastMovement' => $last,
            'denominations' => self::DENOMINATIONS[$account->currency] ?? [],
        ];
    }

    /**
     * État de rapprochement bancaire: the movements ticked on this
     * statement, then the book balance adjusted for the items still
     * outstanding at the statement date.
     */
    public function reconciliation(BankReconciliation $reconciliation): array
    {
        $reconciliation->loadMissing(['account', 'creator', 'transactions']);
        $account = $reconciliation->account;
        $endsOn = $reconciliation->ends_on->toDateString();

        $ticked = $reconciliation->transactions
            ->sortBy([fn ($t) => $t->transaction_date->toDateString(), fn ($t) => $t->id])
            ->values()
            ->map(fn (TreasuryTransaction $transaction) => [
                'transaction' => $transaction,
                'signed' => $transaction->signedAmountFor($account),
            ]);

        $outstanding = TreasuryTransaction::query()
            ->forAccount($account)
            ->whereDate('transaction_date', '<=', $endsOn)
            ->whereDoesntHave('reconciliations', fn (Builder $query) => $query
                ->where('bank_reconciliations.treasury_account_id', $account->id)
                ->where('bank_reconciliations.id', '<=', $reconciliation->id))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(fn (TreasuryTransaction $transaction) => [
                'transaction' => $transaction,
                'signed' => $transaction->signedAmountFor($account),
            ]);

        $outstandingReceipts = $outstanding->filter(fn (array $item) => $item['signed'] > 0)->values();
        $outstandingPayments = $outstanding->filter(fn (array $item) => $item['signed'] < 0)->values();
        $bookBalance = $this->balanceAt($account, $endsOn);
        $adjustedBook = round(
            $bookBalance
            - $outstandingReceipts->sum('signed')
            - $outstandingPayments->sum('signed'),
            2
        );

        return [
            'reconciliation' => $reconciliation,
            'account' => $account,
            'ticked' => $ticked,
            'tickedReceipts' => round($ticked->sum(fn ($item) => max(0, $item['signed'])), 2),
            'tickedPayments' => round($ticked->sum(fn ($item) => max(0, -$item['signed'])), 2),
            'bookBalance' => $bookBalance,
            'outstandingReceipts' => $outstandingReceipts,
            'outstandingPayments' => $outstandingPayments,
            'adjustedBook' => $adjustedBook,
            'residual' => round((float) $reconciliation->statement_closing_balance - $adjustedBook, 2),
        ];
    }

    private function periodMovements(TreasuryAccount $account, string $from, string $to): Builder
    {
        return TreasuryTransaction::query()
            ->forAccount($account)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->orderBy('transaction_date')
            ->orderBy('id');
    }

    private function balanceWhere(TreasuryAccount $account, \Closure $constraint): float
    {
        $sum = fn (\Closure $scope) => (float) TreasuryTransaction::query()->where($constraint)->where($scope)->sum('amount');

        $in = $sum(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('treasury_account_id', $account->id)->where('type', TreasuryTransactionType::Inflow))
            ->orWhere(fn (Builder $query) => $query->where('destination_account_id', $account->id)->where('type', TreasuryTransactionType::Transfer)));
        $out = $sum(fn (Builder $query) => $query
            ->where('treasury_account_id', $account->id)
            ->whereIn('type', [TreasuryTransactionType::Outflow, TreasuryTransactionType::Transfer]));

        return round((float) $account->opening_balance + $in - $out, 2);
    }

    private function totals(Collection $rows): array
    {
        return [
            'opening' => round($rows->sum('opening'), 2),
            'receipts' => round($rows->sum('receipts'), 2),
            'payments' => round($rows->sum('payments'), 2),
            'closing' => round($rows->sum('closing'), 2),
        ];
    }
}
