<?php

use App\Models\Account;
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
    $party = Party::create(['type' => PartyType::Customer, 'name' => 'Client États', 'is_active' => true]);

    $this->invoice = Invoice::create([
        'number' => 'FAC-2026-00800',
        'party_id' => $party->id,
        'status' => InvoiceStatus::Validated,
        'issue_date' => '2026-07-15',
        'due_date' => '2026-08-15',
        'currency' => 'USD',
        'subtotal' => 1000,
        'discount_total' => 0,
        'tax_total' => 160,
        'total' => 1160,
        'created_by' => $this->user->id,
        'validated_at' => now(),
        'validated_by' => $this->user->id,
    ]);
    app(AccountingService::class)->postInvoice($this->invoice, $this->user->id);
});

it('shows a balanced trial balance for one currency and period', function () {
    $this->actingAs($this->user)
        ->get(route('accounting.trial-balance', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
            'currency' => 'USD',
        ]))
        ->assertOk()
        ->assertSee('Balance générale')
        ->assertSee('411')
        ->assertSee('Clients')
        ->assertSee('1 160,00');
});

it('does not mix currencies in the trial balance', function () {
    $response = $this->actingAs($this->user)->get(route('accounting.trial-balance', [
        'date_from' => '2026-01-01',
        'date_to' => '2026-12-31',
        'currency' => 'CDF',
    ]));

    $response->assertOk()->assertSee('Aucun mouvement');
});

it('shows chronological movements and a running balance in the ledger', function () {
    $account = Account::where('code', '411')->firstOrFail();

    $this->actingAs($this->user)
        ->get(route('accounting.ledger', [
            'account' => $account->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
            'currency' => 'USD',
        ]))
        ->assertOk()
        ->assertSee('411 — Clients')
        ->assertSee('FAC-2026-00800')
        ->assertSee('1 160,00');
});

it('exports a real trial balance pdf', function () {
    $response = $this->actingAs($this->user)->get(route('accounting.trial-balance.pdf', [
        'date_from' => '2026-01-01',
        'date_to' => '2026-12-31',
        'currency' => 'USD',
    ]));

    $response->assertOk()
        ->assertDownload('balance-USD-2026-01-01-2026-12-31.pdf')
        ->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

it('protects accounting reports with permissions', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('accounting.trial-balance'))->assertForbidden();
    $this->actingAs($commercial)->get(route('accounting.ledger'))->assertForbidden();
    $this->actingAs($commercial)->get(route('accounting.trial-balance.pdf'))->assertForbidden();
});
