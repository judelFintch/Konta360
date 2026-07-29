<?php

use App\Models\Invoice;
use App\Models\Party;
use App\Models\Quote;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Quotes\Enums\QuoteStatus;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->party = Party::create([
        'type' => PartyType::Customer,
        'name' => 'Client Documents',
        'tax_identifier' => 'NIF-PDF-01',
        'is_active' => true,
    ]);
    $this->quote = Quote::create([
        'number' => 'DEV-2026-00991',
        'party_id' => $this->party->id,
        'status' => QuoteStatus::Sent,
        'issue_date' => '2026-07-29',
        'valid_until' => '2026-08-29',
        'currency' => 'USD',
        'subtotal' => 100,
        'discount_total' => 0,
        'tax_total' => 16,
        'total' => 116,
        'created_by' => $this->user->id,
    ]);
    $this->quote->lines()->create([
        'position' => 1,
        'sku' => 'DOC-01',
        'description' => 'Prestation documentaire',
        'unit' => 'forfait',
        'quantity' => 1,
        'unit_price' => 100,
        'discount_rate' => 0,
        'tax_rate' => 16,
        'subtotal' => 100,
        'discount_amount' => 0,
        'tax_amount' => 16,
        'total' => 116,
    ]);
    $this->invoice = Invoice::create([
        'number' => 'FAC-2026-00991',
        'quote_id' => $this->quote->id,
        'party_id' => $this->party->id,
        'status' => InvoiceStatus::Validated,
        'issue_date' => '2026-07-29',
        'due_date' => '2026-08-29',
        'currency' => 'USD',
        'subtotal' => 100,
        'discount_total' => 0,
        'tax_total' => 16,
        'total' => 116,
        'created_by' => $this->user->id,
    ]);
    $this->invoice->lines()->create([
        'position' => 1,
        'sku' => 'DOC-01',
        'description' => 'Prestation documentaire',
        'unit' => 'forfait',
        'quantity' => 1,
        'unit_price' => 100,
        'discount_rate' => 0,
        'tax_rate' => 16,
        'subtotal' => 100,
        'discount_amount' => 0,
        'tax_amount' => 16,
        'total' => 116,
    ]);
});

it('renders a printable quote with document details', function () {
    $this->actingAs($this->user)
        ->get(route('quotes.print', $this->quote))
        ->assertOk()
        ->assertSee('DEV-2026-00991')
        ->assertSee('Client Documents')
        ->assertSee('Prestation documentaire')
        ->assertSee('Imprimer');
});

it('downloads the quote as a real pdf', function () {
    $response = $this->actingAs($this->user)->get(route('quotes.pdf', $this->quote));

    $response->assertOk()
        ->assertDownload('DEV-2026-00991.pdf')
        ->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

it('renders and downloads invoice documents', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.print', $this->invoice))
        ->assertOk()
        ->assertSee('FAC-2026-00991')
        ->assertSee('Échéance');

    $this->actingAs($this->user)
        ->get(route('invoices.pdf', $this->invoice))
        ->assertOk()
        ->assertDownload('FAC-2026-00991.pdf')
        ->assertHeader('content-type', 'application/pdf');
});

it('protects print and pdf routes with view permissions', function () {
    $userWithoutRole = User::factory()->create();

    $this->actingAs($userWithoutRole)->get(route('quotes.print', $this->quote))->assertForbidden();
    $this->actingAs($userWithoutRole)->get(route('quotes.pdf', $this->quote))->assertForbidden();
    $this->actingAs($userWithoutRole)->get(route('invoices.print', $this->invoice))->assertForbidden();
    $this->actingAs($userWithoutRole)->get(route('invoices.pdf', $this->invoice))->assertForbidden();
});
