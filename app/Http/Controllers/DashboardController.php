<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TreasuryAccount;
use App\Modules\Expenses\Enums\ExpenseStatus;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $currencies = ['CDF', 'USD'];
        $validatedInvoices = Invoice::query()->where('status', InvoiceStatus::Validated)->get();
        $creditNotes = CreditNote::query()->get();
        $payments = Payment::query()->where('status', PaymentStatus::Recorded)->get();
        $expenses = Expense::query()->whereIn('status', [ExpenseStatus::Validated, ExpenseStatus::Paid])->get();

        $metrics = collect($currencies)->mapWithKeys(function (string $currency) use (
            $validatedInvoices, $creditNotes, $payments, $expenses
        ) {
            $invoices = $validatedInvoices->where('currency', $currency);
            $credits = round((float) $creditNotes->where('currency', $currency)->sum('total'), 2);
            $sales = round((float) $invoices->sum('total') - $credits, 2);
            $collected = round((float) $payments->where('currency', $currency)->sum('amount'), 2);
            $expenseTotal = round((float) $expenses->where('currency', $currency)->sum('total'), 2);
            $outstanding = max(0, round($sales - $collected, 2));
            $overdue = $invoices->filter(fn (Invoice $invoice) => $invoice->due_date->isPast() && $invoice->balanceDue() > 0);

            return [$currency => [
                'sales' => $sales,
                'collected' => $collected,
                'expenses' => $expenseTotal,
                'outstanding' => $outstanding,
                'overdue_count' => $overdue->count(),
                'overdue_amount' => round((float) $overdue->sum(fn (Invoice $invoice) => $invoice->balanceDue()), 2),
            ]];
        });

        $treasury = TreasuryAccount::query()->where('is_active', true)->orderBy('name')->get();
        $months = collect(range(5, 0))->map(fn (int $offset) => today()->startOfMonth()->subMonths($offset))
            ->push(today()->startOfMonth())
            ->map(function (Carbon $month) use ($validatedInvoices, $currencies) {
                $invoices = $validatedInvoices->filter(fn (Invoice $invoice) => $invoice->issue_date->isSameMonth($month));

                return [
                    'key' => $month->format('Y-m'),
                    'label' => ucfirst($month->locale('fr')->translatedFormat('M Y')),
                    'totals' => collect($currencies)->mapWithKeys(fn (string $currency) => [
                        $currency => round((float) $invoices->where('currency', $currency)->sum('total'), 2),
                    ])->all(),
                ];
            });
        $maxMonthly = collect($currencies)->mapWithKeys(fn (string $currency) => [
            $currency => max(1, (float) $months->max(fn (array $month) => $month['totals'][$currency])),
        ]);

        return view('dashboard', compact('metrics', 'treasury', 'months', 'maxMonthly'));
    }
}
