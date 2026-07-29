<?php

use App\Models\AccountingPeriod;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Accounting\Enums\PeriodStatus;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Payments\Enums\PaymentMethod;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Administrateur->value);
});

it('creates a non-overlapping accounting period', function () {
    $this->actingAs($this->admin)
        ->post(route('accounting.periods.store'), [
            'name' => 'Exercice 2025',
            'starts_on' => '2025-01-01',
            'ends_on' => '2025-12-31',
        ])
        ->assertRedirect();

    expect(AccountingPeriod::firstOrFail())
        ->name->toBe('Exercice 2025')
        ->status->toBe(PeriodStatus::Open);
});

it('rejects overlapping periods', function () {
    AccountingPeriod::create([
        'name' => 'Exercice 2025',
        'starts_on' => '2025-01-01',
        'ends_on' => '2025-12-31',
        'status' => PeriodStatus::Open,
    ]);

    $this->actingAs($this->admin)
        ->post(route('accounting.periods.store'), [
            'name' => 'Période chevauchante',
            'starts_on' => '2025-06-01',
            'ends_on' => '2026-05-31',
        ])
        ->assertSessionHasErrors('starts_on');

    expect(AccountingPeriod::count())->toBe(1);
});

it('closes a finished period with an audit actor and timestamp', function () {
    $period = AccountingPeriod::create([
        'name' => 'Exercice 2025',
        'starts_on' => '2025-01-01',
        'ends_on' => '2025-12-31',
        'status' => PeriodStatus::Open,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('accounting.periods.close', $period))
        ->assertRedirect();

    expect($period->refresh())
        ->status->toBe(PeriodStatus::Closed)
        ->closed_by->toBe($this->admin->id)
        ->closed_at->not->toBeNull();
});

it('blocks invoice posting inside a closed period and rolls back validation', function () {
    AccountingPeriod::create([
        'name' => 'Exercice 2025',
        'starts_on' => '2025-01-01',
        'ends_on' => '2025-12-31',
        'status' => PeriodStatus::Closed,
        'closed_at' => now(),
        'closed_by' => $this->admin->id,
    ]);
    $party = Party::create(['type' => PartyType::Customer, 'name' => 'Client clôture', 'is_active' => true]);
    $invoice = Invoice::create([
        'party_id' => $party->id,
        'status' => InvoiceStatus::Draft,
        'issue_date' => '2025-12-15',
        'due_date' => '2026-01-15',
        'currency' => 'USD',
        'subtotal' => 100,
        'discount_total' => 0,
        'tax_total' => 16,
        'total' => 116,
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('invoices.validate', $invoice))
        ->assertStatus(409);

    expect($invoice->refresh())
        ->status->toBe(InvoiceStatus::Draft)
        ->number->toBeNull();
});

it('blocks payments dated inside a closed period', function () {
    AccountingPeriod::create([
        'name' => 'Exercice 2025',
        'starts_on' => '2025-01-01',
        'ends_on' => '2025-12-31',
        'status' => PeriodStatus::Closed,
        'closed_at' => now(),
        'closed_by' => $this->admin->id,
    ]);
    $party = Party::create(['type' => PartyType::Customer, 'name' => 'Client clôture', 'is_active' => true]);
    $invoice = Invoice::create([
        'number' => 'FAC-2025-00100',
        'party_id' => $party->id,
        'status' => InvoiceStatus::Validated,
        'issue_date' => '2025-11-01',
        'due_date' => '2025-12-01',
        'currency' => 'USD',
        'subtotal' => 100,
        'discount_total' => 0,
        'tax_total' => 16,
        'total' => 116,
        'created_by' => $this->admin->id,
        'validated_at' => now(),
        'validated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('payments.store', $invoice), [
            'payment_date' => '2025-12-20',
            'amount' => 116,
            'method' => PaymentMethod::Cash->value,
        ])
        ->assertStatus(409);

    expect(Payment::count())->toBe(0);
});

it('only allows administrators with the closing permission to manage periods', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Comptable->value);

    $this->actingAs($accountant)->get(route('accounting.periods.index'))->assertForbidden();
    $this->actingAs($accountant)->post(route('accounting.periods.store'), [
        'name' => 'Exercice 2025',
        'starts_on' => '2025-01-01',
        'ends_on' => '2025-12-31',
    ])->assertForbidden();
});
