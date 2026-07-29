<?php

use App\Models\AccountingEntry;
use App\Models\Expense;
use App\Models\Party;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Expenses\Enums\ExpenseStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Treasury\Enums\TreasuryAccountType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->supplier = Party::create(['type' => PartyType::Supplier, 'name' => 'Fournisseur Test', 'is_active' => true]);
    $this->payload = [
        'supplier_id' => $this->supplier->id,
        'supplier_reference' => 'FF-001',
        'expense_date' => today()->format('Y-m-d'),
        'due_date' => today()->addDays(15)->format('Y-m-d'),
        'description' => 'Fournitures de bureau',
        'currency' => 'USD',
        'subtotal' => 100,
        'tax_total' => 16,
    ];
});

it('creates a draft expense with a server-calculated total', function () {
    $this->actingAs($this->user)->post(route('expenses.store'), $this->payload)->assertRedirect();
    $expense = Expense::firstOrFail();

    expect($expense->number)->toMatch('/^DEP-\d{4}-\d{5}$/')
        ->and($expense->status)->toBe(ExpenseStatus::Draft)
        ->and((float) $expense->total)->toBe(116.0);
});

it('validates an expense and posts the supplier payable', function () {
    $this->actingAs($this->user)->post(route('expenses.store'), $this->payload);
    $expense = Expense::firstOrFail();
    $this->actingAs($this->user)->patch(route('expenses.validate', $expense))->assertRedirect();
    $entry = AccountingEntry::with(['journal', 'lines.account'])->where('source_type', 'expense')->firstOrFail();

    expect($expense->refresh()->status)->toBe(ExpenseStatus::Validated)
        ->and($entry->journal->code)->toBe('AC')
        ->and($entry->lines->pluck('account.code')->all())->toBe(['60', '445', '401'])
        ->and($entry->totalDebit())->toBe(116.0)
        ->and($entry->totalCredit())->toBe(116.0);
});

it('pays an expense from treasury and clears the supplier balance', function () {
    $this->actingAs($this->user)->post(route('expenses.store'), $this->payload);
    $expense = Expense::firstOrFail();
    $this->actingAs($this->user)->patch(route('expenses.validate', $expense));
    $account = TreasuryAccount::create([
        'name' => 'Banque achats', 'type' => TreasuryAccountType::Bank, 'currency' => 'USD',
        'opening_balance' => 500, 'is_active' => true, 'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->post(route('expenses.pay', $expense), [
        'treasury_account_id' => $account->id,
        'payment_date' => today()->format('Y-m-d'),
        'amount' => 116,
        'reference' => 'VIR-ACHAT',
    ])->assertRedirect();

    $paymentEntry = AccountingEntry::with('lines.account')->where('source_type', 'expense_payment')->firstOrFail();
    expect($expense->refresh()->status)->toBe(ExpenseStatus::Paid)
        ->and($expense->balanceDue())->toBe(0.0)
        ->and($account->balance())->toBe(384.0)
        ->and(TreasuryTransaction::where('source_type', 'expense_payment')->count())->toBe(1)
        ->and($paymentEntry->lines->pluck('account.code')->all())->toBe(['401', '512'])
        ->and($paymentEntry->totalDebit())->toBe(116.0)
        ->and($paymentEntry->totalCredit())->toBe(116.0);
});

it('rejects payments above the expense balance or available treasury', function () {
    $expense = Expense::create([
        ...$this->payload, 'number' => 'DEP-2026-00010', 'total' => 116,
        'status' => ExpenseStatus::Validated, 'created_by' => $this->user->id,
    ]);
    $account = TreasuryAccount::create([
        'name' => 'Petite caisse achat', 'type' => TreasuryAccountType::Cash, 'currency' => 'USD',
        'opening_balance' => 50, 'is_active' => true, 'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->post(route('expenses.pay', $expense), [
        'treasury_account_id' => $account->id, 'payment_date' => today()->format('Y-m-d'), 'amount' => 60,
    ])->assertSessionHasErrors('amount');

    expect($expense->payments()->count())->toBe(0);
});

it('protects expense management permissions', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('expenses.index'))->assertForbidden();
    $this->actingAs($commercial)->post(route('expenses.store'), $this->payload)->assertForbidden();
});
