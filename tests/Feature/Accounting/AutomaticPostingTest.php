<?php

use App\Models\AccountingEntry;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Accounting\Services\AccountingService;
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
    $this->party = Party::create(['type' => PartyType::Customer, 'name' => 'Client Comptable', 'is_active' => true]);
    $this->invoice = Invoice::create([
        'party_id' => $this->party->id,
        'status' => InvoiceStatus::Draft,
        'issue_date' => today(),
        'due_date' => today()->addDays(30),
        'currency' => 'USD',
        'subtotal' => 1000,
        'discount_total' => 100,
        'tax_total' => 144,
        'total' => 1044,
        'created_by' => $this->user->id,
    ]);
});

it('posts a balanced sales entry when an invoice is validated', function () {
    $this->actingAs($this->user)
        ->patch(route('invoices.validate', $this->invoice))
        ->assertRedirect();

    $entry = AccountingEntry::with(['journal', 'lines.account'])->firstOrFail();

    expect($entry)
        ->number->toMatch('/^ECR-\d{4}-\d{6}$/')
        ->source_type->toBe('invoice')
        ->source_id->toBe($this->invoice->id)
        ->currency->toBe('USD')
        ->and($entry->journal->code)->toBe('VE')
        ->and($entry->totalDebit())->toBe(1044.0)
        ->and($entry->totalCredit())->toBe(1044.0)
        ->and($entry->lines->pluck('account.code')->all())->toBe(['411', '70', '4431']);
});

it('posts bank and customer lines when a payment is recorded', function () {
    $this->invoice->update([
        'number' => 'FAC-2026-00456',
        'status' => InvoiceStatus::Validated,
        'validated_at' => now(),
        'validated_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->post(route('payments.store', $this->invoice), [
        'payment_date' => today()->format('Y-m-d'),
        'amount' => 400,
        'method' => PaymentMethod::BankTransfer->value,
    ])->assertRedirect();

    $entry = AccountingEntry::with(['journal', 'lines.account'])
        ->where('source_type', 'payment')
        ->firstOrFail();

    expect($entry->journal->code)->toBe('BQ')
        ->and($entry->lines->pluck('account.code')->all())->toBe(['512', '411'])
        ->and($entry->totalDebit())->toBe(400.0)
        ->and($entry->totalCredit())->toBe(400.0);
});

it('creates a balanced mirror entry when a payment is reversed', function () {
    $this->invoice->update([
        'number' => 'FAC-2026-00457',
        'status' => InvoiceStatus::Validated,
        'validated_at' => now(),
        'validated_by' => $this->user->id,
    ]);
    $payment = Payment::create([
        'invoice_id' => $this->invoice->id,
        'number' => 'REG-2026-00457',
        'payment_date' => today(),
        'amount' => 250,
        'currency' => 'USD',
        'method' => PaymentMethod::Cash,
        'status' => PaymentStatus::Recorded,
        'recorded_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->patch(route('payments.reverse', $payment), ['reversal_reason' => 'Erreur de caisse'])
        ->assertRedirect();

    $original = AccountingEntry::with('lines.account')->where('source_type', 'payment')->firstOrFail();
    $reversal = AccountingEntry::with('lines.account')->where('source_type', 'payment_reversal')->firstOrFail();

    expect($original->lines->pluck('account.code')->all())->toBe(['571', '411'])
        ->and($reversal->totalDebit())->toBe($original->totalCredit())
        ->and($reversal->totalCredit())->toBe($original->totalDebit())
        ->and($reversal->lines->first()->debit)->toBe($original->lines->first()->credit);
});

it('does not duplicate an entry when automatic posting is retried', function () {
    $this->invoice->update([
        'number' => 'FAC-2026-00458',
        'status' => InvoiceStatus::Validated,
        'validated_at' => now(),
        'validated_by' => $this->user->id,
    ]);
    $service = app(AccountingService::class);

    $first = $service->postInvoice($this->invoice, $this->user->id);
    $second = $service->postInvoice($this->invoice, $this->user->id);

    expect($second->id)->toBe($first->id)
        ->and(AccountingEntry::count())->toBe(1);
});

it('allows accounting readers to inspect entries and forbids commercial users', function () {
    $service = app(AccountingService::class);
    $this->invoice->update(['number' => 'FAC-2026-00459']);
    $entry = $service->postInvoice($this->invoice, $this->user->id);

    $this->actingAs($this->user)->get(route('accounting.entries.index'))->assertOk();
    $this->actingAs($this->user)->get(route('accounting.entries.show', $entry))->assertOk();

    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);
    $this->actingAs($commercial)->get(route('accounting.entries.index'))->assertForbidden();
});
