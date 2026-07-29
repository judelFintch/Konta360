<?php

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Payment;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Expenses\Enums\ExpenseStatus;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
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

it('shows live financial indicators by currency', function () {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Vue financière')
        ->assertSee('116,00')
        ->assertSee('40,00')
        ->assertSee('58,00')
        ->assertSee('76,00')
        ->assertSee('Banque Dashboard')
        ->assertSee('1 facture(s) échue(s)');
});

it('hides financial indicators from users without business permissions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Vue financière')
        ->assertDontSee('Banque Dashboard');
});
