<?php

use App\Models\CatalogItem;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Quote;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Documents\Enums\DocumentLanguage;
use App\Modules\Documents\Services\CommercialDocumentPresenter;
use App\Modules\Parties\Enums\PartyType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);

    $this->actingAs($this->user)->post(route('parties.store'), [
        'type' => PartyType::Customer->value,
        'name' => 'Mining Contractor Ltd',
        'document_language' => 'en',
        'is_active' => 1,
    ])->assertSessionHasNoErrors();
    $this->party = Party::firstOrFail();

    $item = CatalogItem::create([
        'type' => ItemType::Service, 'sku' => 'LOC-BULL', 'name' => 'August Bulldozer RENTAL', 'unit' => 'h',
        'unit_price' => 75, 'currency' => 'USD', 'tax_rate' => 16, 'is_active' => true,
    ]);
    $this->actingAs($this->user)->post(route('quotes.store'), [
        'party_id' => $this->party->id,
        'issue_date' => '2026-09-01',
        'valid_until' => '2026-09-30',
        'currency' => 'USD',
        'lines' => [['catalog_item_id' => $item->id, 'quantity' => 199, 'discount_rate' => 0]],
    ]);
    $this->actingAs($this->user)->post(route('quotes.invoice', Quote::firstOrFail()));
    $this->invoice = Invoice::firstOrFail();

    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), [
        'issue_date' => '2026-09-01',
        'due_date' => '2026-09-30',
        'deductions' => [
            ['type' => 'advance', 'description' => 'Advance Payment', 'quantity' => 1, 'unit_price' => 10000, 'received_on' => '2026-08-05', 'payment_method' => 'bank_transfer'],
            ['type' => 'client_expense', 'description' => 'Cost Operator', 'quantity' => 3, 'unit_price' => 462.9033],
            ['type' => 'client_expense', 'description' => 'Cost Oil', 'quantity' => 134, 'unit_price' => 4.35],
        ],
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->user)->patch(route('invoices.validate', $this->invoice));
});

it('takes the invoice language from the customer record', function () {
    expect($this->party->document_language)->toBe(DocumentLanguage::English)
        ->and($this->invoice->refresh()->language)->toBe(DocumentLanguage::English);
});

it('prints an English invoice with English wording, numbers and amount in words', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.print', $this->invoice))
        ->assertOk()
        ->assertSee('<html lang="en">', false)
        ->assertSeeInOrder(['Invoice', 'Bill to', 'Description', 'Qty', '199', 'Deductions', 'Advance received', 'Costs borne by the customer'])
        ->assertSeeInOrder(['Total incl. VAT', '17,313.00 USD', 'Total deductions', '11,971.61', 'To be paid', '5,341.39 USD'])
        ->assertSee('Seventeen thousand three hundred thirteen US dollars')
        ->assertSee('Five thousand three hundred forty-one US dollars and thirty-nine cents')
        ->assertSee('Validated')
        ->assertDontSee('Facturé à')
        ->assertDontSee('Net à payer');
});

it('prints the same invoice in French on request, with the same control code', function () {
    $fingerprint = app(CommercialDocumentPresenter::class)->fingerprint($this->invoice->refresh());

    $this->actingAs($this->user)
        ->get(route('invoices.print', [$this->invoice, 'lang' => 'fr']))
        ->assertOk()
        ->assertSee('Facturé à')
        ->assertSee('5 341,39 USD')
        ->assertSee('Cinq mille trois cent quarante-et-un dollars américains et trente-neuf cents')
        ->assertSee($fingerprint);

    $this->actingAs($this->user)
        ->get(route('invoices.print', $this->invoice))
        ->assertSee($fingerprint);
});

it('ignores an unknown language and falls back to the invoice language', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.print', [$this->invoice, 'lang' => 'de']))
        ->assertOk()
        ->assertSee('Bill to');
});

it('downloads the English PDF', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.pdf', [$this->invoice, 'lang' => 'en']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('lets the language of a draft invoice be changed', function () {
    $draft = Invoice::create([
        'party_id' => $this->party->id, 'status' => 'draft', 'issue_date' => '2026-09-01', 'due_date' => '2026-09-30',
        'currency' => 'USD', 'subtotal' => 100, 'discount_total' => 0, 'tax_total' => 16, 'total' => 116, 'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->put(route('invoices.update', $draft), [
        'issue_date' => '2026-09-01', 'due_date' => '2026-09-30', 'language' => 'en',
    ])->assertSessionHasNoErrors();

    expect($draft->refresh()->language)->toBe(DocumentLanguage::English);

    $this->actingAs($this->user)
        ->get(route('invoices.print', $draft))
        ->assertSee('Draft #'.$draft->id)
        ->assertSee('Provisional document');
});

it('spells English amounts with singular and plural currency names', function () {
    $presenter = app(CommercialDocumentPresenter::class);

    expect($presenter->amountInWords(1, 'USD', 'en'))->toBe('One US dollar')
        ->and($presenter->amountInWords(2500.01, 'CDF', 'en'))->toBe('Two thousand five hundred Congolese francs and one centime')
        ->and($presenter->amountInWords(1000000, 'XAF', 'en'))->toBe('One million CFA francs')
        ->and($presenter->amountInWords(2500.01, 'CDF'))->toBe('Deux mille cinq cents francs congolais et un centime');
});
