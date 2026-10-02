<?php

use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Administrateur->value);
});

it('exports all supported reports as UTF-8 CSV downloads', function (string $route, string $filename) {
    $response = $this->actingAs($this->admin)->get(route($route));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload($filename);

    expect($response->streamedContent())->toStartWith("\xEF\xBB\xBF");
})->with([
    ['exports.invoices', 'factures.csv'],
    ['exports.expenses', 'depenses-fournisseurs.csv'],
    ['exports.accounting-entries', 'journal-comptable.csv'],
    ['exports.treasury', 'mouvements-tresorerie.csv'],
    ['exports.audit', 'journal-audit.csv'],
]);

it('neutralizes spreadsheet formulas in exported business text', function () {
    $party = Party::create(['type' => PartyType::Customer, 'name' => '=CMD()', 'is_active' => true]);
    Invoice::create([
        'number' => 'FAC-2026-00001', 'party_id' => $party->id, 'status' => InvoiceStatus::Validated,
        'issue_date' => today(), 'due_date' => today(), 'currency' => 'USD',
        'subtotal' => 100, 'discount_total' => 0, 'tax_total' => 0, 'total' => 100,
        'created_by' => $this->admin->id,
    ]);

    $content = $this->actingAs($this->admin)->get(route('exports.invoices'))->streamedContent();

    expect($content)->toContain("'=CMD()")
        ->not->toContain(';"=CMD()"');
});

it('supports date filters and validates their order', function () {
    $this->actingAs($this->admin)
        ->get(route('exports.invoices', ['from' => '2026-08-01', 'to' => '2026-07-01']))
        ->assertSessionHasErrors('to');
});

it('requires both export and domain permissions', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('exports.invoices'))->assertForbidden();
});
