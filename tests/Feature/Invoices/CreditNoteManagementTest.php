<?php

use App\Models\AccountingEntry;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $party = Party::create(['type' => PartyType::Customer, 'name' => 'Client Avoir', 'is_active' => true]);
    $this->invoice = Invoice::create([
        'number' => 'FAC-2026-00042',
        'party_id' => $party->id,
        'status' => InvoiceStatus::Validated,
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency' => 'USD',
        'subtotal' => 200,
        'discount_total' => 20,
        'tax_total' => 28.80,
        'total' => 208.80,
        'validated_at' => now(),
        'validated_by' => $this->user->id,
        'created_by' => $this->user->id,
    ]);
    $this->line = $this->invoice->lines()->create([
        'position' => 1,
        'sku' => 'SRV-01',
        'description' => 'Service',
        'unit' => 'unité',
        'quantity' => 2,
        'unit_price' => 100,
        'discount_rate' => 10,
        'tax_rate' => 16,
        'subtotal' => 200,
        'discount_amount' => 20,
        'tax_amount' => 28.80,
        'total' => 208.80,
    ]);
});

it('issues a partial credit note and posts a balanced reverse entry', function () {
    $this->actingAs($this->user)
        ->post(route('credit-notes.store', $this->invoice), [
            'issue_date' => '2026-07-15',
            'reason' => 'Prestation partiellement annulée',
            'lines' => [$this->line->id => 1],
        ])
        ->assertRedirect();

    $creditNote = CreditNote::with('lines')->firstOrFail();
    $entry = AccountingEntry::with(['journal', 'lines.account'])
        ->where('source_type', 'credit_note')
        ->firstOrFail();

    expect($creditNote->number)->toMatch('/^AVO-2026-\d{5}$/')
        ->and((float) $creditNote->total)->toBe(104.4)
        ->and((float) $creditNote->lines->first()->quantity)->toBe(1.0)
        ->and($entry->journal->code)->toBe('VE')
        ->and($entry->lines->pluck('account.code')->all())->toBe(['70', '4431', '411'])
        ->and($entry->totalDebit())->toBe(104.4)
        ->and($entry->totalCredit())->toBe(104.4)
        ->and($this->invoice->balanceDue())->toBe(104.4);
});

it('prevents crediting more than the remaining invoiced quantity', function () {
    $payload = [
        'issue_date' => '2026-07-15',
        'reason' => 'Premier retour',
        'lines' => [$this->line->id => 1.5],
    ];
    $this->actingAs($this->user)->post(route('credit-notes.store', $this->invoice), $payload)->assertRedirect();

    $payload['reason'] = 'Retour excessif';
    $payload['lines'][$this->line->id] = 1;
    $this->actingAs($this->user)
        ->from(route('credit-notes.create', $this->invoice))
        ->post(route('credit-notes.store', $this->invoice), $payload)
        ->assertSessionHasErrors("lines.{$this->line->id}");

    expect(CreditNote::count())->toBe(1);
});

it('requires at least one positive quantity and a validated invoice', function () {
    $this->actingAs($this->user)
        ->post(route('credit-notes.store', $this->invoice), [
            'issue_date' => '2026-07-15',
            'reason' => 'Sans ligne',
            'lines' => [$this->line->id => 0],
        ])
        ->assertSessionHasErrors('lines');

    $this->invoice->update(['status' => InvoiceStatus::Draft]);
    $this->actingAs($this->user)
        ->get(route('credit-notes.create', $this->invoice))
        ->assertConflict();
});

it('renders the credit note pages and PDF', function () {
    $this->actingAs($this->user)->post(route('credit-notes.store', $this->invoice), [
        'issue_date' => '2026-07-15',
        'reason' => 'Retour complet',
        'lines' => [$this->line->id => 2],
    ]);
    $creditNote = CreditNote::firstOrFail();

    $this->actingAs($this->user)->get(route('credit-notes.index'))->assertOk()->assertSee($creditNote->number);
    $this->actingAs($this->user)->get(route('credit-notes.show', $creditNote))->assertOk()->assertSee('Retour complet');
    $this->actingAs($this->user)->get(route('credit-notes.print', $creditNote))->assertOk()->assertSee('Avoir');
    $this->actingAs($this->user)->get(route('credit-notes.pdf', $creditNote))->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('shows the create credit note action on eligible invoices', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertSee(route('credit-notes.create', $this->invoice), false)
        ->assertSee('Créer un avoir');

    $this->actingAs($this->user)
        ->get(route('invoices.show', $this->invoice))
        ->assertOk()
        ->assertSee(route('credit-notes.create', $this->invoice), false);
});

it('forbids commercial users from creating credit notes', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('credit-notes.create', $this->invoice))->assertForbidden();
});
