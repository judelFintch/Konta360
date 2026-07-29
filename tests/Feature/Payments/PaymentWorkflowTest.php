<?php

use App\Models\Invoice;
use App\Models\Party;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->party = Party::create(['type' => PartyType::Customer, 'name' => 'Client Payeur', 'is_active' => true]);
    $this->invoice = Invoice::create([
        'number' => 'FAC-2026-00123',
        'party_id' => $this->party->id,
        'status' => InvoiceStatus::Validated,
        'issue_date' => today()->subDays(10),
        'due_date' => today()->addDays(20),
        'currency' => 'USD',
        'subtotal' => 1000,
        'discount_total' => 0,
        'tax_total' => 160,
        'total' => 1160,
        'created_by' => $this->user->id,
        'validated_at' => now(),
        'validated_by' => $this->user->id,
    ]);
});

it('records a partial payment and calculates the remaining balance', function () {
    $this->actingAs($this->user)
        ->post(route('payments.store', $this->invoice), [
            'payment_date' => today()->format('Y-m-d'),
            'amount' => '460.00',
            'method' => PaymentMethod::BankTransfer->value,
            'reference' => 'VIR-001',
        ])
        ->assertRedirect(route('invoices.show', $this->invoice));

    $payment = Payment::firstOrFail();

    expect($payment)
        ->number->toMatch('/^REG-\d{4}-\d{5}$/')
        ->currency->toBe('USD')
        ->status->toBe(PaymentStatus::Recorded)
        ->recorded_by->toBe($this->user->id)
        ->and($this->invoice->paidAmount())->toBe(460.0)
        ->and($this->invoice->balanceDue())->toBe(700.0)
        ->and($this->invoice->paymentLabel())->toBe('Partiellement payée');
});

it('accepts a final payment and marks the invoice as paid', function () {
    Payment::create([
        'invoice_id' => $this->invoice->id,
        'number' => 'REG-2026-001',
        'payment_date' => today(),
        'amount' => 460,
        'currency' => 'USD',
        'method' => PaymentMethod::Cash,
        'status' => PaymentStatus::Recorded,
        'recorded_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->post(route('payments.store', $this->invoice), [
        'payment_date' => today()->format('Y-m-d'),
        'amount' => '700',
        'method' => PaymentMethod::MobileMoney->value,
    ])->assertRedirect();

    expect($this->invoice->balanceDue())->toBe(0.0)
        ->and($this->invoice->paymentLabel())->toBe('Payée');
});

it('rejects an overpayment and leaves the ledger unchanged', function () {
    $this->actingAs($this->user)
        ->post(route('payments.store', $this->invoice), [
            'payment_date' => today()->format('Y-m-d'),
            'amount' => '1160.01',
            'method' => PaymentMethod::Cash->value,
        ])
        ->assertSessionHasErrors('amount');

    expect(Payment::count())->toBe(0);
});

it('only accepts payments for validated invoices', function () {
    $this->invoice->update(['status' => InvoiceStatus::Draft, 'number' => null]);

    $this->actingAs($this->user)
        ->get(route('payments.create', $this->invoice))
        ->assertStatus(409);
});

it('reverses a payment without deleting its audit history', function () {
    $payment = Payment::create([
        'invoice_id' => $this->invoice->id,
        'number' => 'REG-2026-002',
        'payment_date' => today(),
        'amount' => 500,
        'currency' => 'USD',
        'method' => PaymentMethod::Cheque,
        'status' => PaymentStatus::Recorded,
        'recorded_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->patch(route('payments.reverse', $payment), ['reversal_reason' => 'Chèque rejeté'])
        ->assertRedirect();

    expect($payment->refresh())
        ->status->toBe(PaymentStatus::Reversed)
        ->reversal_reason->toBe('Chèque rejeté')
        ->reversed_by->toBe($this->user->id)
        ->reversed_at->not->toBeNull()
        ->and($this->invoice->paidAmount())->toBe(0.0)
        ->and(Payment::count())->toBe(1);
});

it('enforces payment permissions', function () {
    $direction = User::factory()->create();
    $direction->assignRole(Role::Direction->value);

    $this->actingAs($direction)->get(route('payments.index'))->assertForbidden();
    $this->actingAs($direction)->get(route('payments.create', $this->invoice))->assertForbidden();
});
