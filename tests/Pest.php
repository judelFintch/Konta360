<?php

use App\Models\Account;
use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\BankReconciliation;
use App\Models\CatalogItem;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * One record of every kind, owned by the current company.
 */
function seedCompanyData(User $user, string $label): array
{
    $party = Party::forceCreate(['type' => 'both', 'name' => "Client {$label}", 'tax_identifier' => 'NIF-PARTAGE', 'is_active' => true]);
    $item = CatalogItem::forceCreate([
        'type' => 'service', 'sku' => 'SKU-PARTAGE', 'name' => "Article {$label}", 'unit' => 'unit',
        'unit_price' => 100, 'currency' => 'USD', 'tax_rate' => 0, 'is_active' => true,
    ]);
    $quote = Quote::forceCreate([
        'number' => "DEV-{$label}", 'party_id' => $party->id, 'status' => 'sent', 'issue_date' => today(),
        'valid_until' => today()->addMonth(), 'currency' => 'USD', 'created_by' => $user->id,
    ]);
    $invoice = Invoice::forceCreate([
        'number' => "FAC-{$label}", 'party_id' => $party->id, 'status' => 'validated', 'issue_date' => today(),
        'due_date' => today()->addMonth(), 'currency' => 'USD', 'subtotal' => 100, 'discount_total' => 0,
        'tax_total' => 0, 'total' => 100, 'validated_at' => now(), 'validated_by' => $user->id, 'created_by' => $user->id,
    ]);
    $draftInvoice = Invoice::forceCreate([
        'party_id' => $party->id, 'status' => 'draft', 'issue_date' => today(), 'due_date' => today()->addMonth(),
        'currency' => 'USD', 'subtotal' => 100, 'discount_total' => 0, 'tax_total' => 0, 'total' => 100, 'created_by' => $user->id,
    ]);
    $treasuryAccount = TreasuryAccount::forceCreate(['name' => "Banque {$label}", 'type' => 'bank', 'currency' => 'USD', 'created_by' => $user->id]);
    $payment = Payment::forceCreate([
        'invoice_id' => $invoice->id, 'number' => "REG-{$label}", 'payment_date' => today(), 'amount' => 10,
        'currency' => 'USD', 'method' => 'cash', 'status' => 'recorded', 'recorded_by' => $user->id,
    ]);
    $creditNote = CreditNote::forceCreate([
        'number' => "AVO-{$label}", 'invoice_id' => $invoice->id, 'party_id' => $party->id, 'status' => 'issued',
        'issue_date' => today(), 'currency' => 'USD', 'reason' => 'Test', 'subtotal' => 10, 'discount_total' => 0,
        'tax_total' => 0, 'total' => 10, 'created_by' => $user->id,
    ]);
    $expense = Expense::forceCreate([
        'number' => "DEP-{$label}", 'supplier_id' => $party->id, 'expense_date' => today(), 'due_date' => today(),
        'description' => "Dépense {$label}", 'currency' => 'USD', 'subtotal' => 50, 'total' => 50, 'status' => 'draft',
        'created_by' => $user->id,
    ]);
    $transaction = TreasuryTransaction::forceCreate([
        'number' => "TRES-{$label}", 'treasury_account_id' => $treasuryAccount->id, 'type' => 'inflow',
        'transaction_date' => today(), 'amount' => 10, 'currency' => 'USD', 'description' => 'Test', 'created_by' => $user->id,
    ]);
    $reconciliation = BankReconciliation::forceCreate([
        'number' => "RAP-{$label}", 'treasury_account_id' => $treasuryAccount->id, 'starts_on' => today()->startOfMonth(),
        'ends_on' => today(), 'statement_opening_balance' => 0, 'statement_closing_balance' => 10,
        'calculated_closing_balance' => 10, 'difference' => 0, 'currency' => 'USD', 'status' => 'completed',
        'created_by' => $user->id, 'completed_at' => now(), 'completed_by' => $user->id,
    ]);
    $entry = AccountingEntry::forceCreate([
        'number' => "ECR-{$label}", 'journal_id' => Journal::where('code', 'OD')->value('id'), 'entry_date' => today(),
        'label' => "Écriture {$label}", 'currency' => 'USD', 'status' => 'draft', 'created_by' => $user->id,
    ]);
    $period = AccountingPeriod::forceCreate(['name' => "Exercice {$label}", 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
    $asset = FixedAsset::forceCreate([
        'code' => "IMM-{$label}", 'name' => "Véhicule {$label}", 'category' => 'vehicle', 'acquisition_date' => today(),
        'in_service_date' => today(), 'acquisition_cost' => 1000, 'currency' => 'USD', 'useful_life_months' => 60,
        'status' => 'active', 'created_by' => $user->id,
    ]);
    $auditLog = AuditLog::forceCreate(['user_id' => $user->id, 'action' => 'creation', 'http_method' => 'POST', 'description' => "Audit {$label}"]);

    return compact(
        'party', 'item', 'quote', 'invoice', 'draftInvoice', 'treasuryAccount', 'payment', 'creditNote', 'expense',
        'transaction', 'reconciliation', 'entry', 'period', 'asset', 'auditLog',
    ) + ['journal' => Journal::where('code', 'OD')->first(), 'account' => Account::where('code', '571')->first()];
}
