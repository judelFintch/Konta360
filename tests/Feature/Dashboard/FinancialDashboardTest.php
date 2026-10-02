<?php

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Expenses\Enums\ExpenseStatus;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Treasury\Enums\TreasuryAccountType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $party = Party::create(['type' => PartyType::Both, 'name' => 'Tiers Dashboard', 'is_active' => true]);
    $this->invoice = Invoice::create([
        'number' => 'FAC-2026-00999', 'party_id' => $party->id, 'status' => InvoiceStatus::Validated,
        'issue_date' => today(), 'due_date' => today()->subDay(), 'currency' => 'USD',
        'subtotal' => 100, 'discount_total' => 0, 'tax_total' => 16, 'total' => 116,
        'created_by' => $this->user->id,
    ]);
    Payment::create([
        'invoice_id' => $this->invoice->id, 'number' => 'REG-2026-00999', 'payment_date' => today(),
        'amount' => 40, 'currency' => 'USD', 'method' => PaymentMethod::BankTransfer,
        'status' => PaymentStatus::Recorded, 'recorded_by' => $this->user->id,
    ]);
    Expense::create([
        'number' => 'DEP-2026-00999', 'supplier_id' => $party->id, 'expense_date' => today(),
        'due_date' => today(), 'description' => 'Charge dashboard', 'currency' => 'USD',
        'subtotal' => 50, 'tax_total' => 8, 'total' => 58, 'status' => ExpenseStatus::Validated,
        'created_by' => $this->user->id,
    ]);
    TreasuryAccount::create([
        'name' => 'Banque Dashboard', 'type' => TreasuryAccountType::Bank, 'currency' => 'USD',
        'opening_balance' => 140, 'is_active' => true, 'created_by' => $this->user->id,
    ]);
});

it('shows this month indicators by currency', function () {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Chiffre d’affaires du mois')
        ->assertSee('116,00')
        ->assertSee('40,00')
        ->assertSee('58,00')
        ->assertSee('76,00')
        ->assertSee('Banque Dashboard')
        ->assertSee('1 facture(s) échue(s)');
});

it('computes the amount left to collect invoice by invoice', function () {
    // A second invoice, fully paid with an overpayment, must not hide the first one's balance.
    $paid = Invoice::create([
        'number' => 'FAC-2026-00998', 'party_id' => $this->invoice->party_id, 'status' => InvoiceStatus::Validated,
        'issue_date' => today(), 'due_date' => today()->addMonth(), 'currency' => 'USD',
        'subtotal' => 100, 'discount_total' => 0, 'tax_total' => 0, 'total' => 100,
        'created_by' => $this->user->id,
    ]);
    Payment::create([
        'invoice_id' => $paid->id, 'number' => 'REG-2026-00998', 'payment_date' => today(),
        'amount' => 200, 'currency' => 'USD', 'method' => PaymentMethod::BankTransfer,
        'status' => PaymentStatus::Recorded, 'recorded_by' => $this->user->id,
    ]);

    $metrics = $this->actingAs($this->user)->get(route('dashboard'))->viewData('metrics');

    expect($metrics['USD']['outstanding'])->toBe(76.0)
        ->and($metrics['USD']['overdue_amount'])->toBe(76.0);
});

it('charts exactly the last six months without duplicates', function () {
    $months = $this->actingAs($this->user)->get(route('dashboard'))->viewData('months');

    expect($months)->toHaveCount(6)
        ->and($months->last()['label'])->toBe(ucfirst(today()->locale('fr')->translatedFormat('M')))
        ->and($months->pluck('label')->unique())->toHaveCount(6);
});

it('lists overdue invoices and pending quotes', function () {
    Quote::create([
        'number' => 'DEV-2026-00999', 'party_id' => $this->invoice->party_id, 'status' => QuoteStatus::Sent,
        'issue_date' => today(), 'valid_until' => today()->addMonth(), 'currency' => 'USD',
        'subtotal' => 10, 'discount_total' => 0, 'tax_total' => 0, 'total' => 10,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeInOrder(['À relancer', 'FAC-2026-00999', '76,00 USD', 'Devis en attente', 'DEV-2026-00999']);
});

it('hides financial indicators from users without business permissions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Chiffre d’affaires du mois')
        ->assertDontSee('Banque Dashboard');
});

it('only shows currencies that carry activity', function () {
    $metrics = $this->actingAs($this->user)->get(route('dashboard'))->viewData('metrics');

    expect($metrics->keys()->all())->toBe(['USD']);
});
