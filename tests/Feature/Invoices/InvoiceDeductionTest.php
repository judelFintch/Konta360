<?php

use App\Models\AccountingEntry;
use App\Models\CatalogItem;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Documents\Services\CommercialDocumentPresenter;
use App\Modules\Invoices\Enums\DeductionType;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Treasury\Enums\TreasuryAccountType;

/*
 * Equipment rental settled by a statement: 199 h × 75 USD + VAT 16 %, minus
 * an advance and the operator and fuel costs the customer paid directly.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->party = Party::create(['type' => PartyType::Customer, 'name' => 'Chantier Kolwezi', 'is_active' => true]);
    $item = CatalogItem::create([
        'type' => ItemType::Service,
        'sku' => 'LOC-BULL',
        'name' => 'August Bulldozer RENTAL',
        'unit' => 'h',
        'unit_price' => 75,
        'currency' => 'USD',
        'tax_rate' => 16,
        'is_active' => true,
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

    $this->statement = fn (array $overrides = []) => [
        'issue_date' => '2026-09-01',
        'due_date' => '2026-09-30',
        'notes' => null,
        'deductions' => [
            ['type' => 'advance', 'description' => 'Advance Payment', 'quantity' => 1, 'unit_price' => 10000, 'received_on' => '2026-08-05', 'payment_method' => 'bank_transfer', 'reference' => 'VIR-0805'],
            ['type' => 'client_expense', 'description' => 'Cost Operator', 'quantity' => 3, 'unit_price' => 462.9033],
            ['type' => 'client_expense', 'description' => 'Cost Oil', 'quantity' => 134, 'unit_price' => 4.35],
        ],
        ...$overrides,
    ];
});

it('stores deductions with server-computed amounts and the net payable', function () {
    $this->actingAs($this->user)
        ->put(route('invoices.update', $this->invoice), ($this->statement)())
        ->assertRedirect(route('invoices.show', $this->invoice))
        ->assertSessionHasNoErrors();

    $invoice = $this->invoice->refresh()->load('deductions');

    expect($invoice->total)->toBe('17313.00')
        ->and($invoice->deductions)->toHaveCount(3)
        ->and($invoice->deductions->pluck('amount')->all())->toBe(['10000.00', '1388.71', '582.90'])
        ->and($invoice->deductions_total)->toBe('11971.61')
        ->and($invoice->netPayable())->toBe(5341.39)
        ->and($invoice->deductions->first()->type)->toBe(DeductionType::Advance);
});

it('replaces the deductions on each save and accepts none', function () {
    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), ($this->statement)());
    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), ($this->statement)(['deductions' => []]));

    expect($this->invoice->refresh()->deductions()->count())->toBe(0)
        ->and($this->invoice->deductions_total)->toBe('0.00')
        ->and($this->invoice->netPayable())->toBe(17313.0);
});

it('refuses deductions above the total including tax', function () {
    $this->actingAs($this->user)
        ->put(route('invoices.update', $this->invoice), ($this->statement)(['deductions' => [
            ['type' => 'client_expense', 'description' => 'Trop', 'quantity' => 1, 'unit_price' => 17313.01],
        ]]))
        ->assertSessionHasErrors('deductions');

    expect($this->invoice->refresh()->deductions()->count())->toBe(0);
});

it('requires the reception details of an advance, received at the latest on the invoice date', function () {
    $this->actingAs($this->user)
        ->put(route('invoices.update', $this->invoice), ($this->statement)(['deductions' => [
            ['type' => 'advance', 'description' => 'Avance', 'quantity' => 1, 'unit_price' => 100, 'received_on' => '2026-09-02'],
        ]]))
        ->assertSessionHasErrors(['deductions.0.received_on', 'deductions.0.payment_method']);
});

it('requires the receiving treasury account of an advance when accounts exist', function () {
    TreasuryAccount::create([
        'name' => 'Banque USD', 'type' => TreasuryAccountType::Bank, 'currency' => 'USD',
        'opening_balance' => 0, 'is_active' => true, 'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->put(route('invoices.update', $this->invoice), ($this->statement)())
        ->assertSessionHasErrors('deductions.0.treasury_account_id');
});

it('forbids editing deductions without the draft update permission', function () {
    $director = User::factory()->create();
    $director->assignRole(Role::Direction->value);

    $this->actingAs($director)
        ->put(route('invoices.update', $this->invoice), ($this->statement)())
        ->assertForbidden();

    expect($this->invoice->deductions()->count())->toBe(0);
});

it('refuses deductions on a validated invoice', function () {
    $this->actingAs($this->user)->patch(route('invoices.validate', $this->invoice));

    $this->actingAs($this->user)
        ->put(route('invoices.update', $this->invoice), ($this->statement)())
        ->assertStatus(409);
});

it('offsets the customer costs in the sales entry and records the advance as a payment', function () {
    $bank = TreasuryAccount::create([
        'name' => 'Banque USD', 'type' => TreasuryAccountType::Bank, 'currency' => 'USD',
        'opening_balance' => 0, 'is_active' => true, 'created_by' => $this->user->id,
    ]);
    $statement = ($this->statement)();
    $statement['deductions'][0]['treasury_account_id'] = $bank->id;
    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), $statement);

    $this->actingAs($this->user)
        ->patch(route('invoices.validate', $this->invoice))
        ->assertSessionHas('success');

    $invoice = $this->invoice->refresh();
    $sale = AccountingEntry::with('lines.account')->where('source_type', 'invoice')->firstOrFail();
    $payment = Payment::firstOrFail();

    expect($sale->lines->map(fn ($line) => [$line->account->code, (float) $line->debit, (float) $line->credit])->all())->toBe([
        ['411', 17313.0, 0.0],
        ['70', 0.0, 14925.0],
        ['4431', 0.0, 2388.0],
        ['60', 1388.71, 0.0],
        ['411', 0.0, 1388.71],
        ['60', 582.9, 0.0],
        ['411', 0.0, 582.9],
    ])
        ->and($sale->totalDebit())->toBe($sale->totalCredit())
        ->and($payment)
        ->amount->toBe('10000.00')
        ->payment_date->format('Y-m-d')->toBe('2026-08-05')
        ->method->toBe(PaymentMethod::BankTransfer)
        ->status->toBe(PaymentStatus::Recorded)
        ->treasury_account_id->toBe($bank->id)
        ->and($invoice->deductions()->first()->payment_id)->toBe($payment->id)
        ->and(AccountingEntry::where('source_type', 'payment')->where('source_id', $payment->id)->exists())->toBeTrue()
        ->and(TreasuryTransaction::where('source_type', 'payment')->where('source_id', $payment->id)->value('amount'))->toBe('10000.00')
        ->and($invoice->balanceDue())->toBe(5341.39)
        ->and($invoice->advancePaidAmount())->toBe(10000.0)
        ->and($invoice->paymentLabel())->toBe('Partiellement payée');
});

it('lets the remaining net payable be settled and no more', function () {
    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), ($this->statement)());
    $this->actingAs($this->user)->patch(route('invoices.validate', $this->invoice));

    $this->actingAs($this->user)
        ->post(route('payments.store', $this->invoice), ['payment_date' => '2026-09-10', 'amount' => 5341.40, 'method' => 'cash'])
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->user)
        ->post(route('payments.store', $this->invoice), ['payment_date' => '2026-09-10', 'amount' => 5341.39, 'method' => 'cash'])
        ->assertSessionHasNoErrors();

    expect($this->invoice->refresh()->balanceDue())->toBe(0.0)
        ->and($this->invoice->paymentLabel())->toBe('Payée');
});

it('reverses the offsets with the sales entry when the invoice is cancelled', function () {
    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), ($this->statement)(['deductions' => [
        ['type' => 'client_expense', 'description' => 'Cost Oil', 'quantity' => 134, 'unit_price' => 4.35],
    ]]));
    $this->actingAs($this->user)->patch(route('invoices.validate', $this->invoice));
    $this->actingAs($this->user)->patch(route('invoices.cancel', $this->invoice))->assertSessionHas('success');

    $reversal = AccountingEntry::with('lines.account')->where('source_type', 'invoice_reversal')->firstOrFail();

    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Cancelled)
        ->and($reversal->lines->where('account.code', '60')->sum('credit'))->toBe(582.9);
});

it('prints the deductions, the net payable and its amount in words', function () {
    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), ($this->statement)());
    $this->actingAs($this->user)->patch(route('invoices.validate', $this->invoice));

    $this->actingAs($this->user)
        ->get(route('invoices.print', $this->invoice))
        ->assertOk()
        ->assertSeeInOrder(['Déductions', 'Advance Payment', 'Cost Operator', '1 388,71', 'Cost Oil', '582,90'])
        ->assertSee('Net à payer')
        ->assertSee('5 341,39 USD')
        ->assertSee('Cinq mille trois cent quarante-et-un dollars américains et trente-neuf cents');

    $this->actingAs($this->user)->get(route('invoices.show', $this->invoice))->assertOk()->assertSee('Net à payer');
});

it('shows the saved deductions on the draft edit form', function () {
    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), ($this->statement)());

    $this->actingAs($this->user)
        ->get(route('invoices.edit', $this->invoice))
        ->assertOk()
        ->assertSee('Cost Operator')
        ->assertSee('462.9033');
});

it('includes deductions in the fingerprint only when there are some', function () {
    $presenter = app(CommercialDocumentPresenter::class);
    $this->invoice->update(['number' => 'FAC-TEST']);
    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), ($this->statement)(['deductions' => []]));
    $without = $presenter->fingerprint($this->invoice->fresh());

    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), ($this->statement)());
    $with = $presenter->fingerprint($this->invoice->fresh());

    $this->actingAs($this->user)->put(route('invoices.update', $this->invoice), ($this->statement)(['deductions' => []]));

    expect($with)->not->toBe($without)
        ->and($presenter->fingerprint($this->invoice->fresh()))->toBe($without);
});

it('points to the deductions from a draft invoice without any', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.show', $this->invoice))
        ->assertOk()
        ->assertSee('Ajouter des déductions')
        ->assertSee(route('invoices.edit', $this->invoice).'#deductions', false);

    $this->actingAs($this->user)
        ->get(route('invoices.edit', $this->invoice))
        ->assertSee('id="deductions"', false);
});

it('hides the deductions entry once the invoice is validated', function () {
    $this->actingAs($this->user)->patch(route('invoices.validate', $this->invoice));

    $this->actingAs($this->user)
        ->get(route('invoices.show', $this->invoice))
        ->assertOk()
        ->assertDontSee('Ajouter des déductions');
});
