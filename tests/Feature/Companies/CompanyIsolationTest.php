<?php

use App\Models\Account;
use App\Models\AccountingEntry;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Companies\Exceptions\MissingCompanyContext;
use App\Modules\Companies\Services\CurrentCompany;
use App\Modules\Documents\Services\CommercialDocumentPresenter;

/*
 * ADR 0002: a user of company A can neither list, open, change nor
 * reference anything that belongs to company B.
 */

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->companyA = Company::current();
    $this->userA = User::factory()->create();
    $this->userA->assignRole(Role::Administrateur->value);

    $this->companyB = Company::factory()->create(['name' => 'Société Bêta']);
    $this->userB = User::factory()->forCompany($this->companyB)->create(['name' => 'Utilisateur Bêta']);
    $this->userB->assignRole(Role::Administrateur->value);

    $this->b = $this->asCompany($this->companyB, fn () => seedCompanyData($this->userB, 'BETA'));
});

it('keeps each company’s records out of the other company’s queries', function () {
    expect(Party::count())->toBe(0)
        ->and(Invoice::count())->toBe(0)
        ->and(Account::pluck('company_id')->unique()->all())->toBe([$this->companyA->id]);

    $this->asCompany($this->companyB, function () {
        expect(Party::count())->toBe(1)
            ->and(Invoice::count())->toBe(2)
            ->and(AuditLog::count())->toBe(1);
    });
});

it('refuses to read or write business data without a current company', function () {
    app(CurrentCompany::class)->forget();

    expect(fn () => Party::count())->toThrow(MissingCompanyContext::class)
        ->and(fn () => Party::forceCreate(['type' => 'customer', 'name' => 'X', 'is_active' => true]))->toThrow(MissingCompanyContext::class);
});

it('does not list another company’s records', function (string $route, string $hidden) {
    $this->actingAs($this->userA)->get(route($route))
        ->assertOk()
        ->assertDontSee($hidden);
})->with([
    'tiers' => ['parties.index', 'Client BETA'],
    'catalogue' => ['catalog.index', 'Article BETA'],
    'devis' => ['quotes.index', 'DEV-BETA'],
    'factures' => ['invoices.index', 'FAC-BETA'],
    'avoirs' => ['credit-notes.index', 'AVO-BETA'],
    'règlements' => ['payments.index', 'REG-BETA'],
    'dépenses' => ['expenses.index', 'Dépense BETA'],
    'trésorerie' => ['treasury.index', 'Banque BETA'],
    'rapprochements' => ['treasury.reconciliations.index', 'RAP-BETA'],
    'écritures' => ['accounting.entries.index', 'Écriture BETA'],
    'périodes' => ['accounting.periods.index', 'Exercice BETA'],
    'immobilisations' => ['fixed-assets.index', 'Véhicule BETA'],
    'audit' => ['audit-logs.index', 'Audit BETA'],
    'utilisateurs' => ['administration.users.index', 'Utilisateur Bêta'],
    'tableau de bord' => ['dashboard', 'Société Bêta'],
]);

it('does not open another company’s records', function (string $route, string $record, string $parameter) {
    $this->actingAs($this->userA)
        ->get(route($route, [$parameter => $this->b[$record]]))
        ->assertNotFound();
})->with([
    ['parties.edit', 'party', 'party'],
    ['catalog.edit', 'item', 'catalog_item'],
    ['quotes.show', 'quote', 'quote'],
    ['quotes.edit', 'quote', 'quote'],
    ['quotes.pdf', 'quote', 'quote'],
    ['invoices.show', 'invoice', 'invoice'],
    ['invoices.print', 'invoice', 'invoice'],
    ['invoices.pdf', 'invoice', 'invoice'],
    ['invoices.edit', 'draftInvoice', 'invoice'],
    ['credit-notes.show', 'creditNote', 'creditNote'],
    ['credit-notes.create', 'invoice', 'invoice'],
    ['payments.create', 'invoice', 'invoice'],
    ['expenses.show', 'expense', 'expense'],
    ['treasury.reconciliations.show', 'reconciliation', 'reconciliation'],
    ['accounting.entries.show', 'entry', 'entry'],
    ['fixed-assets.show', 'asset', 'fixedAsset'],
    ['fixed-assets.edit', 'asset', 'fixedAsset'],
    ['audit-logs.show', 'auditLog', 'auditLog'],
]);

it('does not change another company’s records', function (string $method, string $route, string $record, string $parameter, array $payload) {
    $this->actingAs($this->userA)
        ->call($method, route($route, [$parameter => $this->b[$record]]), $payload)
        ->assertNotFound();
})->with([
    ['PUT', 'parties.update', 'party', 'party', ['type' => 'customer', 'name' => 'Piraté', 'is_active' => 1]],
    ['PATCH', 'quotes.send', 'quote', 'quote', []],
    ['POST', 'quotes.invoice', 'quote', 'quote', []],
    ['PATCH', 'invoices.validate', 'draftInvoice', 'invoice', []],
    ['PATCH', 'invoices.cancel', 'invoice', 'invoice', []],
    ['POST', 'payments.store', 'invoice', 'invoice', ['payment_date' => '2026-01-01', 'amount' => 1, 'method' => 'cash']],
    ['PATCH', 'payments.reverse', 'payment', 'payment', ['reversal_reason' => 'Piratage']],
    ['PATCH', 'expenses.validate', 'expense', 'expense', []],
    ['PATCH', 'accounting.entries.post', 'entry', 'entry', []],
    ['PATCH', 'accounting.periods.close', 'period', 'period', []],
    ['POST', 'fixed-assets.depreciations.store', 'asset', 'fixedAsset', []],
]);

it('does not let a form reference another company’s records', function () {
    $this->actingAs($this->userA);

    $this->post(route('quotes.store'), [
        'party_id' => $this->b['party']->id, 'issue_date' => '2026-07-29', 'valid_until' => '2026-08-29', 'currency' => 'USD',
        'lines' => [['catalog_item_id' => $this->b['item']->id, 'quantity' => 1, 'discount_rate' => 0]],
    ])->assertSessionHasErrors(['party_id', 'lines.0.catalog_item_id']);

    $this->post(route('expenses.store'), [
        'supplier_id' => $this->b['party']->id, 'expense_date' => '2026-07-29', 'due_date' => '2026-07-29',
        'description' => 'Test', 'currency' => 'USD', 'subtotal' => 10, 'tax_total' => 0,
    ])->assertSessionHasErrors('supplier_id');

    $this->post(route('treasury.transactions.store'), [
        'treasury_account_id' => $this->b['treasuryAccount']->id, 'type' => 'inflow',
        'transaction_date' => today()->toDateString(), 'amount' => 10, 'description' => 'Test',
    ])->assertSessionHasErrors('treasury_account_id');

    $this->post(route('accounting.entries.store'), [
        'journal_id' => $this->b['journal']->id, 'entry_date' => '2026-07-29', 'label' => 'Test', 'currency' => 'USD',
        'lines' => [
            ['account_id' => $this->b['account']->id, 'description' => 'D', 'debit' => 10],
            ['account_id' => $this->b['account']->id, 'description' => 'C', 'credit' => 10],
        ],
    ])->assertSessionHasErrors(['journal_id', 'lines.0.account_id', 'lines.1.account_id']);

    $this->post(route('treasury.reconciliations.store'), [
        'treasury_account_id' => $this->b['treasuryAccount']->id, 'transactions' => [$this->b['transaction']->id],
    ])->assertSessionHasErrors(['treasury_account_id', 'transactions.0']);

    expect(AccountingEntry::count())->toBe(0)
        ->and(Expense::count())->toBe(0);
});

it('applies uniqueness per company only', function () {
    $this->actingAs($this->userA);

    $this->post(route('parties.store'), [
        'type' => 'customer', 'name' => 'Client Alpha', 'tax_identifier' => 'NIF-PARTAGE', 'is_active' => 1,
    ])->assertSessionHasNoErrors();

    $this->post(route('catalog.store'), [
        'type' => 'service', 'sku' => 'SKU-PARTAGE', 'name' => 'Article Alpha', 'unit' => 'unit',
        'unit_price' => 10, 'currency' => 'USD', 'tax_rate' => 0, 'is_active' => 1,
    ])->assertSessionHasNoErrors();

    $this->post(route('parties.store'), [
        'type' => 'customer', 'name' => 'Doublon', 'tax_identifier' => 'NIF-PARTAGE', 'is_active' => 1,
    ])->assertSessionHasErrors('tax_identifier');
});

it('numbers documents per company, each starting at 1', function () {
    $party = Party::forceCreate(['type' => 'customer', 'name' => 'Client Alpha', 'is_active' => true]);
    $draft = Invoice::forceCreate([
        'party_id' => $party->id, 'status' => 'draft', 'issue_date' => '2026-07-29', 'due_date' => '2026-08-29',
        'currency' => 'USD', 'subtotal' => 100, 'discount_total' => 0, 'tax_total' => 0, 'total' => 100, 'created_by' => $this->userA->id,
    ]);
    $draft->lines()->forceCreate([
        'position' => 1, 'sku' => 'S', 'description' => 'Service', 'unit' => 'unit', 'quantity' => 1, 'unit_price' => 100,
        'discount_rate' => 0, 'tax_rate' => 0, 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 100,
    ]);
    $draftB = $this->asCompany($this->companyB, function () {
        $this->b['draftInvoice']->lines()->forceCreate([
            'position' => 1, 'sku' => 'S', 'description' => 'Service', 'unit' => 'unit', 'quantity' => 1, 'unit_price' => 100,
            'discount_rate' => 0, 'tax_rate' => 0, 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 100,
        ]);

        return $this->b['draftInvoice'];
    });

    $this->actingAs($this->userA)->patch(route('invoices.validate', $draft))->assertSessionHasNoErrors();
    $this->actingAs($this->userB)->patch(route('invoices.validate', $draftB))->assertSessionHasNoErrors();

    $year = $draftB->issue_date->format('Y');
    expect($draft->refresh()->number)->toBe('FAC-2026-00001')
        ->and($this->asCompany($this->companyB, fn () => $draftB->refresh()->number))->toBe("FAC-{$year}-00001");
});

it('shows the issuing company on the public verification page', function () {
    $presenter = app(CommercialDocumentPresenter::class);
    $invoice = $this->b['invoice'];

    $this->get($presenter->verificationUrl($invoice))
        ->assertOk()
        ->assertSee('Société Bêta')
        ->assertSee('FAC-BETA');
});

it('lets an administrator manage only their own company’s users', function () {
    $this->actingAs($this->userA);

    $this->get(route('administration.users.edit', $this->userB))->assertNotFound();
    $this->put(route('administration.users.update', $this->userB), [
        'name' => 'Piraté', 'email' => $this->userB->email, 'role' => Role::Commercial->value, 'is_active' => 0,
    ])->assertNotFound();

    $this->post(route('administration.users.store'), [
        'name' => 'Nouveau', 'email' => 'nouveau@alpha.test', 'password' => 'password',
        'password_confirmation' => 'password', 'role' => Role::Comptable->value,
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'nouveau@alpha.test')->value('company_id'))->toBe($this->companyA->id)
        ->and($this->userB->refresh()->name)->toBe('Utilisateur Bêta');
});

it('records the audit trail in the acting user’s company', function () {
    $this->actingAs($this->userB)->post(route('parties.store'), [
        'type' => 'customer', 'name' => 'Client Gamma', 'is_active' => 1,
    ])->assertSessionHasNoErrors();

    expect(AuditLog::count())->toBe(0);
    $this->asCompany($this->companyB, fn () => expect(AuditLog::count())->toBe(2));
});

it('changes only the current company’s settings', function () {
    $this->actingAs($this->userA)->put(route('administration.company.update'), [
        'name' => 'Alpha SARL', 'default_currency' => 'USD', 'default_tax_rate' => 16, 'default_payment_days' => 15,
        'default_quote_validity_days' => 20, 'quote_prefix' => 'DV', 'invoice_prefix' => 'FT',
        'credit_note_prefix' => 'NC', 'number_padding' => 6,
    ])->assertSessionHasNoErrors();

    expect($this->companyA->refresh()->name)->toBe('Alpha SARL')
        ->and($this->companyB->refresh()->name)->toBe('Société Bêta');
});
