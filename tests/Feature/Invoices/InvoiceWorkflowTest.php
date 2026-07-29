<?php

use App\Models\CatalogItem;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Quote;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Quotes\Enums\QuoteStatus;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->party = Party::create(['type' => PartyType::Customer, 'name' => 'Client Facture', 'is_active' => true]);
    $this->item = CatalogItem::create([
        'type' => ItemType::Service,
        'sku' => 'SRV-FAC',
        'name' => 'Audit',
        'unit' => 'jour',
        'unit_price' => 100,
        'currency' => 'USD',
        'tax_rate' => 16,
        'is_active' => true,
    ]);

    $this->actingAs($this->user)->post(route('quotes.store'), [
        'party_id' => $this->party->id,
        'issue_date' => '2026-07-29',
        'valid_until' => '2026-08-29',
        'currency' => 'USD',
        'lines' => [['catalog_item_id' => $this->item->id, 'quantity' => 2, 'discount_rate' => 10]],
    ]);
    $this->quote = Quote::firstOrFail();
    $this->quote->update(['status' => QuoteStatus::Sent]);
});

it('converts a sent quote into one draft invoice with exact snapshots', function () {
    $this->actingAs($this->user)
        ->post(route('quotes.invoice', $this->quote))
        ->assertRedirect();

    $invoice = Invoice::with('lines')->firstOrFail();

    expect($invoice)
        ->status->toBe(InvoiceStatus::Draft)
        ->number->toBeNull()
        ->quote_id->toBe($this->quote->id)
        ->party_id->toBe($this->party->id)
        ->total->toBe('208.80')
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first()->description)->toBe('Audit')
        ->and($this->quote->refresh()->status)->toBe(QuoteStatus::Accepted);
});

it('converts a draft quote directly and marks it as accepted', function () {
    $this->quote->update(['status' => QuoteStatus::Draft]);

    $this->actingAs($this->user)
        ->post(route('quotes.invoice', $this->quote))
        ->assertRedirect();

    expect(Invoice::firstOrFail()->status)->toBe(InvoiceStatus::Draft)
        ->and($this->quote->refresh()->status)->toBe(QuoteStatus::Accepted);
});

it('prevents converting the same quote twice', function () {
    $this->actingAs($this->user)->post(route('quotes.invoice', $this->quote))->assertRedirect();

    $this->actingAs($this->user)
        ->post(route('quotes.invoice', $this->quote))
        ->assertStatus(409);

    expect(Invoice::count())->toBe(1);
});

it('validates and permanently numbers a draft invoice', function () {
    $this->actingAs($this->user)->post(route('quotes.invoice', $this->quote));
    $invoice = Invoice::firstOrFail();

    $this->actingAs($this->user)
        ->patch(route('invoices.validate', $invoice))
        ->assertRedirect();

    expect($invoice->refresh())
        ->status->toBe(InvoiceStatus::Validated)
        ->number->toMatch('/^FAC-\d{4}-\d{5}$/')
        ->validated_by->toBe($this->user->id)
        ->validated_at->not->toBeNull();
});

it('lets a commercial convert but not validate an invoice', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)
        ->post(route('quotes.invoice', $this->quote))
        ->assertRedirect();

    $invoice = Invoice::firstOrFail();
    $this->actingAs($commercial)
        ->patch(route('invoices.validate', $invoice))
        ->assertForbidden();
});

it('updates draft dates but rejects changes after validation', function () {
    $this->actingAs($this->user)->post(route('quotes.invoice', $this->quote));
    $invoice = Invoice::firstOrFail();

    $this->actingAs($this->user)
        ->put(route('invoices.update', $invoice), [
            'issue_date' => '2026-08-01',
            'due_date' => '2026-09-01',
            'notes' => 'Paiement à trente jours.',
        ])
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->refresh()->notes)->toBe('Paiement à trente jours.');

    $this->actingAs($this->user)->patch(route('invoices.validate', $invoice));
    $this->actingAs($this->user)
        ->put(route('invoices.update', $invoice), [
            'issue_date' => '2026-08-02',
            'due_date' => '2026-09-02',
        ])
        ->assertStatus(409);
});

it('cancels an invoice with an audit timestamp and actor', function () {
    $this->actingAs($this->user)->post(route('quotes.invoice', $this->quote));
    $invoice = Invoice::firstOrFail();

    $this->actingAs($this->user)
        ->patch(route('invoices.cancel', $invoice))
        ->assertRedirect();

    expect($invoice->refresh())
        ->status->toBe(InvoiceStatus::Cancelled)
        ->cancelled_by->toBe($this->user->id)
        ->cancelled_at->not->toBeNull();
});
