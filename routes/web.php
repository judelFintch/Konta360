<?php

use App\Http\Controllers\AccountingEntryController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\CompanyDataController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Platform\CompanyController as PlatformCompanyController;
use App\Http\Controllers\Platform\PlanController as PlatformPlanController;
use App\Http\Controllers\Platform\SubscriptionPaymentController as PlatformSubscriptionPaymentController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\UserManagementController;
use App\Modules\Administration\Enums\Permission;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::get('verification/{type}/{id}/{token}', DocumentVerificationController::class)
    ->whereIn('type', ['quote', 'invoice', 'credit_note'])
    ->whereNumber('id')
    ->whereAlphaNumeric('token')
    ->middleware('throttle:30,1')
    ->name('documents.verify');
Route::get('verification/{type}/{id}/{token}/logo', [DocumentVerificationController::class, 'logo'])
    ->whereIn('type', ['quote', 'invoice', 'credit_note'])
    ->whereNumber('id')
    ->whereAlphaNumeric('token')
    ->middleware('throttle:30,1')
    ->name('documents.verify.logo');

Route::view('legal/terms', 'legal.terms')->name('legal.terms');
Route::view('legal/privacy', 'legal.privacy')->name('legal.privacy');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::resource('parties', PartyController::class)
    ->only(['index', 'create', 'store', 'edit', 'update'])
    ->middleware(['auth', 'verified', 'permission:'.Permission::PartiesManage->value]);

Route::resource('catalog', CatalogItemController::class)
    ->parameters(['catalog' => 'catalog_item'])
    ->only(['index', 'create', 'store', 'edit', 'update'])
    ->middleware(['auth', 'verified', 'permission:'.Permission::CatalogManage->value]);

Route::resource('fixed-assets', FixedAssetController::class)
    ->parameters(['fixed-assets' => 'fixedAsset'])
    ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
    ->middleware(['auth', 'verified', 'permission:'.Permission::FixedAssetsManage->value]);
Route::post('fixed-assets/{fixedAsset}/depreciations', [FixedAssetController::class, 'postDepreciations'])
    ->middleware(['auth', 'verified', 'permission:'.Permission::FixedAssetsManage->value])
    ->name('fixed-assets.depreciations.store');

Route::resource('administration/users', UserManagementController::class)
    ->parameters(['users' => 'user'])
    ->only(['index', 'create', 'store', 'edit', 'update'])
    ->middleware(['auth', 'verified', 'permission:'.Permission::UsersManage->value])
    ->names('administration.users');
Route::get('administration/company', [CompanySettingController::class, 'edit'])
    ->middleware(['auth', 'verified', 'permission:'.Permission::SettingsManage->value])
    ->name('administration.company.edit');
Route::put('administration/company', [CompanySettingController::class, 'update'])
    ->middleware(['auth', 'verified', 'permission:'.Permission::SettingsManage->value])
    ->name('administration.company.update');
Route::get('subscription', [SubscriptionController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('subscription.show');
Route::post('subscription/payments', [SubscriptionController::class, 'storePayment'])
    ->middleware(['auth', 'verified', 'throttle:10,1'])
    ->name('subscription.payments.store');

Route::prefix('administration/data')->name('administration.data.')
    ->middleware(['auth', 'verified', 'permission:'.Permission::SettingsManage->value])
    ->group(function () {
        Route::get('/', [CompanyDataController::class, 'show'])->name('show');
        Route::get('export', [CompanyDataController::class, 'export'])->middleware('throttle:5,1')->name('export');
        Route::post('terms', [CompanyDataController::class, 'acceptTerms'])->name('terms.accept');
        Route::post('closure', [CompanyDataController::class, 'requestClosure'])->name('closure.request');
        Route::delete('closure', [CompanyDataController::class, 'cancelClosure'])->name('closure.cancel');
    });

Route::get('company-assets/{type}', [CompanySettingController::class, 'asset'])
    ->middleware(['auth', 'verified'])
    ->name('administration.company.asset');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('quotes', QuoteController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('quotes/{quote}/print', [QuoteController::class, 'print'])->name('quotes.print');
    Route::get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
    Route::patch('quotes/{quote}/send', [QuoteController::class, 'markAsSent'])->name('quotes.send');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::post('quotes/{quote}/invoice', [InvoiceController::class, 'convert'])->name('quotes.invoice');
    Route::patch('invoices/{invoice}/validate', [InvoiceController::class, 'validateInvoice'])->name('invoices.validate');
    Route::patch('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

    Route::get('credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
    Route::get('invoices/{invoice}/credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
    Route::post('invoices/{invoice}/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
    Route::get('credit-notes/{creditNote}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
    Route::get('credit-notes/{creditNote}/print', [CreditNoteController::class, 'print'])->name('credit-notes.print');
    Route::get('credit-notes/{creditNote}/pdf', [CreditNoteController::class, 'pdf'])->name('credit-notes.pdf');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('invoices/{invoice}/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::patch('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');

    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::patch('expenses/{expense}/validate', [ExpenseController::class, 'validateExpense'])->name('expenses.validate');
    Route::post('expenses/{expense}/payments', [ExpenseController::class, 'pay'])->name('expenses.pay');

    Route::get('treasury', [TreasuryController::class, 'index'])->name('treasury.index');
    Route::post('treasury/accounts', [TreasuryController::class, 'storeAccount'])->name('treasury.accounts.store');
    Route::get('treasury/transactions/create', [TreasuryController::class, 'createTransaction'])->name('treasury.transactions.create');
    Route::post('treasury/transactions', [TreasuryController::class, 'storeTransaction'])->name('treasury.transactions.store');
    Route::post('treasury/payments/{payment}/assign', [TreasuryController::class, 'assignPayment'])->name('treasury.payments.assign');
    Route::get('treasury/reconciliations', [BankReconciliationController::class, 'index'])->name('treasury.reconciliations.index');
    Route::get('treasury/reconciliations/create', [BankReconciliationController::class, 'create'])->name('treasury.reconciliations.create');
    Route::post('treasury/reconciliations', [BankReconciliationController::class, 'store'])->name('treasury.reconciliations.store');
    Route::get('treasury/reconciliations/{reconciliation}', [BankReconciliationController::class, 'show'])->name('treasury.reconciliations.show');

    Route::get('accounting/entries', [AccountingEntryController::class, 'index'])->name('accounting.entries.index');
    Route::get('accounting/entries/create', [AccountingEntryController::class, 'create'])->name('accounting.entries.create');
    Route::post('accounting/entries', [AccountingEntryController::class, 'store'])->name('accounting.entries.store');
    Route::get('accounting/entries/{entry}', [AccountingEntryController::class, 'show'])->name('accounting.entries.show');
    Route::patch('accounting/entries/{entry}/post', [AccountingEntryController::class, 'post'])->name('accounting.entries.post');
    Route::get('accounting/trial-balance', [AccountingReportController::class, 'trialBalance'])->name('accounting.trial-balance');
    Route::get('accounting/trial-balance/pdf', [AccountingReportController::class, 'trialBalancePdf'])->name('accounting.trial-balance.pdf');
    Route::get('accounting/ledger', [AccountingReportController::class, 'ledger'])->name('accounting.ledger');
    Route::get('accounting/periods', [AccountingPeriodController::class, 'index'])->name('accounting.periods.index');
    Route::post('accounting/periods', [AccountingPeriodController::class, 'store'])->name('accounting.periods.store');
    Route::patch('accounting/periods/{period}/close', [AccountingPeriodController::class, 'close'])->name('accounting.periods.close');
    Route::get('financial-statements/income-statement', [AccountingReportController::class, 'incomeStatement'])->name('financial-statements.income-statement');
    Route::get('financial-statements/income-statement/pdf', [AccountingReportController::class, 'incomeStatementPdf'])->name('financial-statements.income-statement.pdf');
    Route::get('financial-statements/balance-sheet', [AccountingReportController::class, 'balanceSheet'])->name('financial-statements.balance-sheet');
    Route::get('financial-statements/balance-sheet/pdf', [AccountingReportController::class, 'balanceSheetPdf'])->name('financial-statements.balance-sheet.pdf');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    Route::get('exports/invoices.csv', [ReportExportController::class, 'invoices'])->name('exports.invoices');
    Route::get('exports/expenses.csv', [ReportExportController::class, 'expenses'])->name('exports.expenses');
    Route::get('exports/accounting-entries.csv', [ReportExportController::class, 'accountingEntries'])->name('exports.accounting-entries');
    Route::get('exports/treasury.csv', [ReportExportController::class, 'treasury'])->name('exports.treasury');
    Route::get('exports/audit.csv', [ReportExportController::class, 'audit'])->name('exports.audit');
});

Route::prefix('platform')->name('platform.')->middleware(['auth', 'verified', 'platform'])->group(function () {
    Route::get('companies', [PlatformCompanyController::class, 'index'])->name('companies.index');
    Route::patch('companies/{company}/suspend', [PlatformCompanyController::class, 'suspend'])->name('companies.suspend');
    Route::patch('companies/{company}/reactivate', [PlatformCompanyController::class, 'reactivate'])->name('companies.reactivate');
    Route::patch('companies/{company}/exempt', [PlatformCompanyController::class, 'toggleExempt'])->name('companies.exempt');
    Route::patch('companies/{company}/close', [PlatformCompanyController::class, 'close'])->name('companies.close');
    Route::get('plans', [PlatformPlanController::class, 'index'])->name('plans.index');
    Route::put('plans/{plan}', [PlatformPlanController::class, 'update'])->name('plans.update');
    Route::get('payments', [PlatformSubscriptionPaymentController::class, 'index'])->name('payments.index');
    Route::patch('payments/{payment}/confirm', [PlatformSubscriptionPaymentController::class, 'confirm'])->whereNumber('payment')->name('payments.confirm');
    Route::patch('payments/{payment}/reject', [PlatformSubscriptionPaymentController::class, 'reject'])->whereNumber('payment')->name('payments.reject');
    Route::post('logout', [PlatformCompanyController::class, 'logout'])->name('logout');
});

require __DIR__.'/auth.php';
