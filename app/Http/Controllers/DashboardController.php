<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\TreasuryAccount;
use App\Modules\Expenses\Enums\ExpenseStatus;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Quotes\Enums\QuoteStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $company = Company::current();
        $monthStart = today()->startOfMonth();

        $validatedInvoices = Invoice::query()
            ->where('status', InvoiceStatus::Validated)
            ->with(['party', 'recordedPayments', 'creditNotes'])
            ->get();
        $validatedIds = $validatedInvoices->modelKeys();
        $creditNotes = CreditNote::query()->whereIn('invoice_id', $validatedIds)->get();
        $payments = Payment::query()->where('status', PaymentStatus::Recorded)->get();
        $expenses = Expense::query()->whereIn('status', [ExpenseStatus::Validated, ExpenseStatus::Paid])->get();
        $treasury = TreasuryAccount::query()->where('is_active', true)->orderBy('name')->get();

        // Remaining amount per invoice, from the eager-loaded relations (no query per invoice).
        $balances = $validatedInvoices->mapWithKeys(fn (Invoice $invoice) => [
            $invoice->id => max(0, round(
                (float) $invoice->total
                - (float) $invoice->creditNotes->sum('total')
                - (float) $invoice->recordedPayments->sum('amount'),
                2
            )),
        ]);
        $overdueInvoices = $validatedInvoices
            ->filter(fn (Invoice $invoice) => $invoice->due_date->lt(today()) && $balances[$invoice->id] > 0)
            ->sortBy('due_date');

        $currencies = $this->activeCurrencies($company, $validatedInvoices, $payments, $expenses, $treasury);

        $metrics = $currencies->mapWithKeys(function (string $currency) use (
            $validatedInvoices, $creditNotes, $payments, $expenses, $balances, $overdueInvoices, $monthStart
        ) {
            $inMonth = fn (Carbon $date) => $date->gte($monthStart);
            $invoices = $validatedInvoices->where('currency', $currency);
            $overdue = $overdueInvoices->where('currency', $currency);

            return [$currency => [
                'sales' => round(
                    (float) $invoices->filter(fn ($invoice) => $inMonth($invoice->issue_date))->sum('total')
                    - (float) $creditNotes->where('currency', $currency)->filter(fn ($note) => $inMonth($note->issue_date))->sum('total'),
                    2
                ),
                'collected' => round((float) $payments->where('currency', $currency)->filter(fn ($payment) => $inMonth($payment->payment_date))->sum('amount'), 2),
                'expenses' => round((float) $expenses->where('currency', $currency)->filter(fn ($expense) => $inMonth($expense->expense_date))->sum('total'), 2),
                'outstanding' => round((float) $invoices->sum(fn ($invoice) => $balances[$invoice->id]), 2),
                'overdue_count' => $overdue->count(),
                'overdue_amount' => round((float) $overdue->sum(fn ($invoice) => $balances[$invoice->id]), 2),
            ]];
        });

        $months = collect(range(5, 0))
            ->map(fn (int $offset) => $monthStart->copy()->subMonths($offset))
            ->map(fn (Carbon $month) => [
                'label' => ucfirst($month->locale('fr')->translatedFormat('M')),
                'year' => $month->year,
                'totals' => $currencies->mapWithKeys(fn (string $currency) => [
                    $currency => round((float) $validatedInvoices
                        ->where('currency', $currency)
                        ->filter(fn (Invoice $invoice) => $invoice->issue_date->isSameMonth($month))
                        ->sum('total'), 2),
                ])->all(),
            ]);
        // Only chart currencies that sold something over the period.
        $maxMonthly = $currencies
            ->mapWithKeys(fn (string $currency) => [
                $currency => (float) $months->max(fn (array $month) => $month['totals'][$currency]),
            ])
            ->filter(fn (float $max) => $max > 0);

        $pendingQuotes = Quote::query()
            ->where('status', QuoteStatus::Sent)
            ->with('party')
            ->orderBy('valid_until')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'company' => $company,
            'metrics' => $metrics,
            'treasury' => $treasury,
            'months' => $months,
            'maxMonthly' => $maxMonthly,
            'overdueInvoices' => $overdueInvoices->take(5),
            'balances' => $balances,
            'pendingQuotes' => $pendingQuotes,
            'draftInvoicesCount' => Invoice::query()->where('status', InvoiceStatus::Draft)->count(),
        ]);
    }

    /**
     * Currencies that actually carry activity, the default one first;
     * the default currency alone when nothing has been recorded yet.
     */
    private function activeCurrencies(Company $company, Collection ...$sources): Collection
    {
        $default = $company->default_currency ?: 'CDF';
        $used = collect($sources)->flatMap(fn (Collection $items) => $items->pluck('currency'))->filter()->unique();

        return $used->isEmpty()
            ? collect([$default])
            : $used->sortBy(fn (string $currency) => $currency === $default ? '' : $currency)->values();
    }
}
