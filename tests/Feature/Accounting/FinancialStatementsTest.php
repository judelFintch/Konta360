<?php

use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $party = Party::create(['type' => PartyType::Customer, 'name' => 'Client États Financiers', 'is_active' => true]);
    $invoice = Invoice::create([
        'number' => 'FAC-2026-00900',
        'party_id' => $party->id,
        'status' => InvoiceStatus::Validated,
        'issue_date' => '2026-06-30',
        'due_date' => '2026-07-30',
        'currency' => 'USD',
        'subtotal' => 1000,
        'discount_total' => 0,
        'tax_total' => 160,
        'total' => 1160,
        'created_by' => $this->user->id,
        'validated_at' => now(),
        'validated_by' => $this->user->id,
    ]);
    app(AccountingService::class)->postInvoice($invoice, $this->user->id);
});

it('calculates revenue expenses and net income for a period', function () {
    $this->actingAs($this->user)
        ->get(route('financial-statements.income-statement', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
            'currency' => 'USD',
        ]))
        ->assertOk()
        ->assertSee('Compte de résultat')
        ->assertSee('70')
        ->assertSee('Ventes')
        ->assertSee('1 000,00')
        ->assertSee('Résultat net');
});

it('calculates a balanced balance sheet including retained income', function () {
    $this->actingAs($this->user)
        ->get(route('financial-statements.balance-sheet', [
            'date_to' => '2026-12-31',
            'currency' => 'USD',
        ]))
        ->assertOk()
        ->assertSee('Bilan')
        ->assertSee('411')
        ->assertSee('Clients')
        ->assertSee('4431')
        ->assertSee('Taxes sur ventes')
        ->assertSee('Résultat cumulé')
        ->assertSee('Actif 1 160,00 = Passif 1 160,00 USD');
});

it('keeps financial statements separated by currency', function () {
    $this->actingAs($this->user)
        ->get(route('financial-statements.income-statement', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
            'currency' => 'CDF',
        ]))
        ->assertOk()
        ->assertSee('Total des produits')
        ->assertSee('0,00 CDF');
});

it('exports income statement and balance sheet as pdf files', function () {
    $income = $this->actingAs($this->user)->get(route('financial-statements.income-statement.pdf', [
        'date_from' => '2026-01-01',
        'date_to' => '2026-12-31',
        'currency' => 'USD',
    ]));
    $income->assertOk()
        ->assertDownload('compte-resultat-USD-2026-01-01-2026-12-31.pdf')
        ->assertHeader('content-type', 'application/pdf');
    expect($income->getContent())->toStartWith('%PDF');

    $balance = $this->actingAs($this->user)->get(route('financial-statements.balance-sheet.pdf', [
        'date_to' => '2026-12-31',
        'currency' => 'USD',
    ]));
    $balance->assertOk()
        ->assertDownload('bilan-USD-2026-12-31.pdf')
        ->assertHeader('content-type', 'application/pdf');
});

it('allows direction to view financial statements and forbids commercial users', function () {
    $direction = User::factory()->create();
    $direction->assignRole(Role::Direction->value);
    $this->actingAs($direction)->get(route('financial-statements.income-statement'))->assertOk();
    $this->actingAs($direction)->get(route('financial-statements.balance-sheet'))->assertOk();

    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);
    $this->actingAs($commercial)->get(route('financial-statements.income-statement'))->assertForbidden();
    $this->actingAs($commercial)->get(route('financial-statements.balance-sheet'))->assertForbidden();
});
