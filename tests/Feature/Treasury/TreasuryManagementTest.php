<?php

use App\Models\AccountingEntry;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Payment;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Treasury\Enums\TreasuryAccountType;
use App\Modules\Treasury\Enums\TreasuryTransactionType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
});

it('creates a treasury account and displays its opening balance', function () {
    $this->actingAs($this->user)->post(route('treasury.accounts.store'), [
        'name' => 'Banque USD',
        'type' => TreasuryAccountType::Bank->value,
        'currency' => 'USD',
        'opening_balance' => 1000,
    ])->assertRedirect();

    $account = TreasuryAccount::firstOrFail();

    expect($account->type)->toBe(TreasuryAccountType::Bank)
        ->and($account->balance())->toBe(1000.0);
    $this->actingAs($this->user)->get(route('treasury.index'))->assertOk()->assertSee('Banque USD');
});

it('records inflows and outflows with balanced accounting entries', function () {
    $account = TreasuryAccount::create([
        'name' => 'Caisse USD',
        'type' => TreasuryAccountType::Cash,
        'currency' => 'USD',
        'opening_balance' => 500,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->post(route('treasury.transactions.store'), [
        'treasury_account_id' => $account->id,
        'type' => TreasuryTransactionType::Inflow->value,
        'transaction_date' => today()->format('Y-m-d'),
        'amount' => 200,
        'description' => 'Apport de caisse',
    ])->assertRedirect(route('treasury.index'));

    $this->actingAs($this->user)->post(route('treasury.transactions.store'), [
        'treasury_account_id' => $account->id,
        'type' => TreasuryTransactionType::Outflow->value,
        'transaction_date' => today()->format('Y-m-d'),
        'amount' => 150,
        'description' => 'Frais divers',
    ])->assertRedirect(route('treasury.index'));

    $entries = AccountingEntry::with(['journal', 'lines.account'])
        ->where('source_type', 'treasury_transaction')->get();

    expect($account->balance())->toBe(550.0)
        ->and(TreasuryTransaction::count())->toBe(2)
        ->and($entries)->toHaveCount(2)
        ->and($entries->first()->journal->code)->toBe('CA')
        ->and($entries->first()->lines->pluck('account.code')->all())->toBe(['571', '75'])
        ->and($entries->last()->lines->pluck('account.code')->all())->toBe(['65', '571'])
        ->and($entries->every(fn ($entry) => $entry->totalDebit() === $entry->totalCredit()))->toBeTrue();
});

it('transfers funds between accounts in the same currency', function () {
    $bank = TreasuryAccount::create([
        'name' => 'Banque principale', 'type' => TreasuryAccountType::Bank, 'currency' => 'USD',
        'opening_balance' => 1000, 'is_active' => true, 'created_by' => $this->user->id,
    ]);
    $cash = TreasuryAccount::create([
        'name' => 'Petite caisse', 'type' => TreasuryAccountType::Cash, 'currency' => 'USD',
        'opening_balance' => 100, 'is_active' => true, 'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->post(route('treasury.transactions.store'), [
        'treasury_account_id' => $bank->id,
        'destination_account_id' => $cash->id,
        'type' => TreasuryTransactionType::Transfer->value,
        'transaction_date' => today()->format('Y-m-d'),
        'amount' => 250,
        'description' => 'Alimentation caisse',
    ])->assertRedirect(route('treasury.index'));

    $entry = AccountingEntry::with('lines.account')->where('source_type', 'treasury_transaction')->firstOrFail();

    expect($bank->balance())->toBe(750.0)
        ->and($cash->balance())->toBe(350.0)
        ->and($entry->lines->pluck('account.code')->all())->toBe(['571', '512'])
        ->and($entry->totalDebit())->toBe(250.0)
        ->and($entry->totalCredit())->toBe(250.0);
});

it('rejects an insufficient balance and a cross-currency transfer', function () {
    $usd = TreasuryAccount::create([
        'name' => 'Banque USD', 'type' => TreasuryAccountType::Bank, 'currency' => 'USD',
        'opening_balance' => 100, 'is_active' => true, 'created_by' => $this->user->id,
    ]);
    $cdf = TreasuryAccount::create([
        'name' => 'Caisse CDF', 'type' => TreasuryAccountType::Cash, 'currency' => 'CDF',
        'opening_balance' => 1000, 'is_active' => true, 'created_by' => $this->user->id,
    ]);
    $base = [
        'treasury_account_id' => $usd->id,
        'transaction_date' => today()->format('Y-m-d'),
        'amount' => 150,
        'description' => 'Test de contrôle',
    ];

    $this->actingAs($this->user)
        ->post(route('treasury.transactions.store'), [...$base, 'type' => TreasuryTransactionType::Outflow->value])
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->user)
        ->post(route('treasury.transactions.store'), [
            ...$base,
            'amount' => 50,
            'type' => TreasuryTransactionType::Transfer->value,
            'destination_account_id' => $cdf->id,
        ])
        ->assertSessionHasErrors('destination_account_id');

    expect(TreasuryTransaction::count())->toBe(0);
});

it('protects treasury with its dedicated permission', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('treasury.index'))->assertForbidden();
    $this->actingAs($this->user)->get(route('treasury.index'))->assertOk();
});

it('assigns a historical payment to treasury without duplicating accounting', function () {
    $party = Party::create(['type' => PartyType::Customer, 'name' => 'Ancien client', 'is_active' => true]);
    $invoice = Invoice::create([
        'number' => 'FAC-2026-00010', 'party_id' => $party->id, 'status' => InvoiceStatus::Validated,
        'issue_date' => today(), 'due_date' => today(), 'currency' => 'USD', 'subtotal' => 300,
        'discount_total' => 0, 'tax_total' => 0, 'total' => 300, 'created_by' => $this->user->id,
    ]);
    $payment = Payment::create([
        'invoice_id' => $invoice->id, 'number' => 'REG-2026-00010', 'payment_date' => today(),
        'amount' => 300, 'currency' => 'USD', 'method' => PaymentMethod::BankTransfer,
        'status' => PaymentStatus::Recorded, 'recorded_by' => $this->user->id,
    ]);
    $account = TreasuryAccount::create([
        'name' => 'Compte historique', 'type' => TreasuryAccountType::Bank, 'currency' => 'USD',
        'opening_balance' => 0, 'is_active' => true, 'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->get(route('treasury.index'))->assertOk()->assertSee('REG-2026-00010');
    $this->actingAs($this->user)
        ->post(route('treasury.payments.assign', $payment), ['treasury_account_id' => $account->id])
        ->assertRedirect();

    expect($payment->refresh()->treasury_account_id)->toBe($account->id)
        ->and($account->balance())->toBe(300.0)
        ->and(TreasuryTransaction::where('source_type', 'payment')->count())->toBe(1)
        ->and(AccountingEntry::count())->toBe(0);

    $this->actingAs($this->user)
        ->post(route('treasury.payments.assign', $payment), ['treasury_account_id' => $account->id])
        ->assertConflict();
});
