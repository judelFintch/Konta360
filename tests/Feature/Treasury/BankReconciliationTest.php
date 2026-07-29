<?php

use App\Models\BankReconciliation;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Treasury\Enums\BankReconciliationStatus;
use App\Modules\Treasury\Enums\TreasuryAccountType;
use App\Modules\Treasury\Enums\TreasuryTransactionType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->account = TreasuryAccount::create([
        'name' => 'Banque rapprochement',
        'type' => TreasuryAccountType::Bank,
        'currency' => 'USD',
        'opening_balance' => 1000,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);
    $this->inflow = TreasuryTransaction::create([
        'number' => 'TRES-2026-00001',
        'treasury_account_id' => $this->account->id,
        'type' => TreasuryTransactionType::Inflow,
        'transaction_date' => '2026-07-10',
        'amount' => 200,
        'currency' => 'USD',
        'description' => 'Encaissement',
        'created_by' => $this->user->id,
    ]);
    $this->outflow = TreasuryTransaction::create([
        'number' => 'TRES-2026-00002',
        'treasury_account_id' => $this->account->id,
        'type' => TreasuryTransactionType::Outflow,
        'transaction_date' => '2026-07-12',
        'amount' => 50,
        'currency' => 'USD',
        'description' => 'Frais bancaire',
        'created_by' => $this->user->id,
    ]);
    $this->payload = [
        'treasury_account_id' => $this->account->id,
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-07-31',
        'statement_opening_balance' => 1000,
        'statement_closing_balance' => 1150,
        'transactions' => [$this->inflow->id, $this->outflow->id],
    ];
});

it('completes a zero-difference bank reconciliation', function () {
    $this->actingAs($this->user)
        ->post(route('treasury.reconciliations.store'), $this->payload)
        ->assertRedirect();

    $reconciliation = BankReconciliation::with('transactions')->firstOrFail();

    expect($reconciliation->number)->toMatch('/^RAP-2026-\d{5}$/')
        ->and($reconciliation->status)->toBe(BankReconciliationStatus::Completed)
        ->and((float) $reconciliation->calculated_closing_balance)->toBe(1150.0)
        ->and((float) $reconciliation->difference)->toBe(0.0)
        ->and($reconciliation->transactions)->toHaveCount(2);
});

it('rejects a reconciliation while an unexplained difference remains', function () {
    $payload = $this->payload;
    $payload['statement_closing_balance'] = 1200;

    $this->actingAs($this->user)
        ->post(route('treasury.reconciliations.store'), $payload)
        ->assertSessionHasErrors('transactions');

    expect(BankReconciliation::count())->toBe(0);
});

it('does not reconcile the same movements twice for one bank account', function () {
    $this->actingAs($this->user)->post(route('treasury.reconciliations.store'), $this->payload);

    $this->actingAs($this->user)
        ->post(route('treasury.reconciliations.store'), $this->payload)
        ->assertSessionHasErrors('transactions');

    expect(BankReconciliation::count())->toBe(1);
});

it('renders reconciliation selection and history pages', function () {
    $url = route('treasury.reconciliations.create', [
        'account_id' => $this->account->id,
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-07-31',
    ]);
    $this->actingAs($this->user)->get($url)->assertOk()->assertSee('TRES-2026-00001');
    $this->actingAs($this->user)->post(route('treasury.reconciliations.store'), $this->payload);
    $reconciliation = BankReconciliation::firstOrFail();

    $this->actingAs($this->user)->get(route('treasury.reconciliations.index'))->assertOk()->assertSee($reconciliation->number);
    $this->actingAs($this->user)->get(route('treasury.reconciliations.show', $reconciliation))->assertOk()->assertSee('1 150,00');
});

it('protects bank reconciliation with treasury permission', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('treasury.reconciliations.index'))->assertForbidden();
});
