<?php

use App\Models\CatalogItem;
use App\Models\Party;
use App\Models\Quote;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Quotes\Enums\QuoteStatus;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->party = Party::create([
        'type' => PartyType::Customer,
        'name' => 'Client Katanga',
        'is_active' => true,
    ]);
    $this->item = CatalogItem::create([
        'type' => ItemType::Service,
        'sku' => 'SRV-DEV',
        'name' => 'Conseil',
        'unit' => 'heure',
        'unit_price' => 100,
        'currency' => 'USD',
        'tax_rate' => 16,
        'is_active' => true,
    ]);
});

it('creates a numbered draft quote and calculates its totals on the server', function () {
    $response = $this->actingAs($this->user)->post(route('quotes.store'), [
        'party_id' => $this->party->id,
        'issue_date' => '2026-07-29',
        'valid_until' => '2026-08-28',
        'currency' => 'USD',
        'notes' => 'Validité de trente jours.',
        'lines' => [[
            'catalog_item_id' => $this->item->id,
            'quantity' => 2,
            'discount_rate' => 10,
        ]],
    ]);

    $quote = Quote::with('lines')->firstOrFail();
    $response->assertRedirect(route('quotes.show', $quote));

    expect($quote)
        ->number->toMatch('/^DEV-2026-\d{5}$/')
        ->status->toBe(QuoteStatus::Draft)
        ->subtotal->toBe('200.00')
        ->discount_total->toBe('20.00')
        ->tax_total->toBe('28.80')
        ->total->toBe('208.80')
        ->and($quote->lines)->toHaveCount(1)
        ->and($quote->lines->first()->description)->toBe('Conseil');
});

it('rejects catalog items whose currency differs from the quote', function () {
    $this->actingAs($this->user)
        ->post(route('quotes.store'), [
            'party_id' => $this->party->id,
            'issue_date' => '2026-07-29',
            'valid_until' => '2026-08-28',
            'currency' => 'CDF',
            'lines' => [[
                'catalog_item_id' => $this->item->id,
                'quantity' => 1,
                'discount_rate' => 0,
            ]],
        ])
        ->assertSessionHasErrors('lines.0.catalog_item_id');

    expect(Quote::count())->toBe(0);
});

it('allows direction to view quotes but not create them', function () {
    $direction = User::factory()->create();
    $direction->assignRole(Role::Direction->value);

    $this->actingAs($direction)->get(route('quotes.index'))->assertOk();
    $this->actingAs($direction)->get(route('quotes.create'))->assertForbidden();
});

it('updates a draft and replaces its calculated lines', function () {
    $this->actingAs($this->user)->post(route('quotes.store'), [
        'party_id' => $this->party->id,
        'issue_date' => '2026-07-29',
        'valid_until' => '2026-08-28',
        'currency' => 'USD',
        'lines' => [['catalog_item_id' => $this->item->id, 'quantity' => 1, 'discount_rate' => 0]],
    ]);
    $quote = Quote::firstOrFail();

    $this->actingAs($this->user)
        ->put(route('quotes.update', $quote), [
            'party_id' => $this->party->id,
            'issue_date' => '2026-07-30',
            'valid_until' => '2026-08-30',
            'currency' => 'USD',
            'lines' => [['catalog_item_id' => $this->item->id, 'quantity' => 3, 'discount_rate' => 0]],
        ])
        ->assertRedirect(route('quotes.show', $quote));

    expect($quote->refresh())
        ->subtotal->toBe('300.00')
        ->total->toBe('348.00')
        ->and($quote->lines()->count())->toBe(1);
});

it('marks a draft as sent and prevents further editing', function () {
    $this->actingAs($this->user)->post(route('quotes.store'), [
        'party_id' => $this->party->id,
        'issue_date' => '2026-07-29',
        'valid_until' => '2026-08-28',
        'currency' => 'USD',
        'lines' => [['catalog_item_id' => $this->item->id, 'quantity' => 1, 'discount_rate' => 0]],
    ]);
    $quote = Quote::firstOrFail();

    $this->actingAs($this->user)
        ->patch(route('quotes.send', $quote))
        ->assertRedirect();

    expect($quote->refresh()->status)->toBe(QuoteStatus::Sent);
    $this->actingAs($this->user)->get(route('quotes.edit', $quote))->assertStatus(409);
});

it('requires valid dates a customer and at least one line', function () {
    $this->actingAs($this->user)
        ->post(route('quotes.store'), [
            'party_id' => 999999,
            'issue_date' => '2026-07-29',
            'valid_until' => '2026-07-28',
            'currency' => 'USD',
            'lines' => [],
        ])
        ->assertSessionHasErrors(['party_id', 'valid_until', 'lines']);
});
