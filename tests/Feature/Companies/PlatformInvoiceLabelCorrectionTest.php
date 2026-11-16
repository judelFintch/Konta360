<?php

use App\Models\AccountingEntry;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLabelCorrection;
use App\Models\InvoiceLine;
use App\Models\Party;
use App\Models\User;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->operator = User::factory()->platformAdmin()->create([
        'name' => 'Super-admin',
        'email_verified_at' => now(),
    ]);
    $this->company = Company::factory()->onPlan('pro')->create(['name' => 'Société à corriger']);

    $this->asCompany($this->company, function () {
        $party = Party::forceCreate([
            'type' => PartyType::Customer,
            'name' => 'Client de la facture',
            'is_active' => true,
        ]);
        $this->invoice = Invoice::forceCreate([
            'number' => 'FAC-2026-00001',
            'party_id' => $party->id,
            'status' => InvoiceStatus::Validated,
            'issue_date' => today(),
            'due_date' => today()->addDays(30),
            'currency' => 'USD',
            'subtotal' => 1000,
            'discount_total' => 0,
            'tax_total' => 160,
            'total' => 1160,
            'validated_at' => now(),
            'validated_by' => $this->operator->id,
            'created_by' => $this->operator->id,
        ]);
        $this->line = InvoiceLine::forceCreate([
            'invoice_id' => $this->invoice->id,
            'position' => 1,
            'sku' => 'SERVICE-1',
            'description' => 'Prestation sans TVA',
            'unit' => 'service',
            'quantity' => 1,
            'unit_price' => 1000,
            'discount_rate' => 0,
            'tax_rate' => 16,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 160,
            'total' => 1160,
        ]);

        app(AccountingService::class)->postInvoice($this->invoice, $this->operator->id);
    });
});

function labelPayload($test, array $extra = []): array
{
    return array_replace([
        'descriptions' => [$test->line->id => 'Prestation corrigée'],
        'original_descriptions' => [$test->line->id => $test->line->description],
        'label_correction_reason' => 'Erreur de saisie.',
    ], $extra);
}

it('corrects labels with history while preserving the entire invoice and accounting entries', function () {
    $beforeInvoice = $this->asCompany($this->company, fn () => $this->invoice->fresh()->getAttributes());
    $beforeLine = $this->asCompany($this->company, fn () => $this->line->fresh()->getAttributes());
    $beforeEntries = $this->asCompany($this->company, fn () => AccountingEntry::withoutGlobalScopes()->with('lines')->get()->toArray());
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->get(route('invoices.show', $this->invoice))->assertOk()->assertSee('Corriger les libellés');
    $this->patch(route('invoices.label-correction', $this->invoice), labelPayload($this, ['total' => 1, 'status' => 'draft']))
        ->assertRedirect(route('invoices.show', $this->invoice))->assertSessionHasNoErrors();
    $this->asCompany($this->company, function () use ($beforeInvoice, $beforeLine, $beforeEntries) {
        expect($this->invoice->fresh()->getAttributes())->toBe($beforeInvoice)
            ->and($this->line->fresh()->getAttributes())->toBe(array_replace($beforeLine, ['description' => 'Prestation corrigée']))
            ->and(AccountingEntry::withoutGlobalScopes()->with('lines')->get()->toArray())->toBe($beforeEntries);
        $correction = InvoiceLabelCorrection::firstOrFail();
        expect($correction->created_by)->toBe($this->operator->id)
            ->and($correction->line_changes[0]['old_description'])->toBe('Prestation sans TVA')
            ->and($correction->line_changes[0]['new_description'])->toBe('Prestation corrigée')
            ->and(AuditLog::where('route_name', 'invoices.label-correction')->firstOrFail()->metadata['invoice_label_correction_id'])->toBe($correction->id);
    });
    $this->get(route('invoices.show', $this->invoice))->assertOk()->assertSee('Historique des corrections de libellés')->assertSee('Erreur de saisie.');
    $this->get(route('invoices.print', $this->invoice))->assertOk()->assertSee('Prestation corrigée');
});

it('rejects invalid and stale corrections without changing any labels', function ($override, $error) {
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->patch(route('invoices.label-correction', $this->invoice), labelPayload($this, $override))->assertSessionHasErrors($error);
    $this->asCompany($this->company, function () {
        expect($this->line->fresh()->description)->toBe('Prestation sans TVA')
            ->and(InvoiceLabelCorrection::count())->toBe(0);
    });
})->with([
    'foreign line' => [['descriptions' => [999999 => 'Autre']], 'descriptions'],
    'stale form' => [['original_descriptions' => [1 => 'Ancienne version']], 'descriptions'],
    'blank reason' => [['label_correction_reason' => '  '], 'label_correction_reason'],
    'unchanged' => [['descriptions' => [1 => 'Prestation sans TVA']], 'descriptions'],
]);

it('refuses correction on draft or cancelled invoices', function ($status) {
    $this->asCompany($this->company, fn () => $this->invoice->update(['status' => $status]));
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->patch(route('invoices.label-correction', $this->invoice), labelPayload($this))->assertStatus(409);
    expect(InvoiceLabelCorrection::withoutGlobalScopes()->count())->toBe(0);
})->with(['draft', 'cancelled']);

it('requires an active super-admin support session', function () {
    $this->actingAs($this->operator)->patch(route('invoices.label-correction', $this->invoice), labelPayload($this))
        ->assertRedirect(route('platform.dashboard'));
    expect(InvoiceLabelCorrection::withoutGlobalScopes()->count())->toBe(0);
});

it('denies correction to a company administrator', function () {
    $admin = User::factory()->create(['company_id' => $this->company->id, 'email_verified_at' => now()]);
    $admin->assignRole(Role::Administrateur->value);
    $this->actingAs($admin)->patch(route('invoices.label-correction', $this->invoice), labelPayload($this))->assertForbidden();
    expect(InvoiceLabelCorrection::withoutGlobalScopes()->count())->toBe(0);
});

it('prevents a support session from correcting another company invoice', function () {
    $otherCompany = Company::factory()->onPlan('pro')->create();
    $this->actingAs($this->operator)->post(route('platform.companies.access', $otherCompany));
    $this->patch(route('invoices.label-correction', $this->invoice), labelPayload($this))->assertNotFound();
    expect(InvoiceLabelCorrection::withoutGlobalScopes()->count())->toBe(0);
});
