<?php

use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceTaxCorrection;
use App\Models\Party;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Accounting\Enums\PeriodStatus;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\CreditNotes\Enums\CreditNoteStatus;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;

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

it('lets a platform super-admin correct the VAT on an unpaid validated invoice and posts the difference', function () {
    $this->actingAs($this->operator)
        ->post(route('platform.companies.access', $this->company))
        ->assertRedirect(route('dashboard'));

    $this->get(route('invoices.show', $this->invoice))
        ->assertOk()
        ->assertSee('Correction de la TVA')
        ->assertSee('tax_rates['.$this->line->id.']');

    $this->patch(route('invoices.tax-correction', $this->invoice), [
        'tax_rates' => [$this->line->id => '0'],
        'reason' => 'Article configuré par erreur avec TVA.',
    ])->assertRedirect(route('invoices.show', $this->invoice))
        ->assertSessionHasNoErrors();

    $this->asCompany($this->company, function () {
        $invoice = $this->invoice->fresh('lines');
        $correction = InvoiceTaxCorrection::query()->firstOrFail();
        $adjustment = AccountingEntry::query()
            ->where('source_type', 'invoice_tax_correction')
            ->where('source_id', $correction->id)
            ->with('lines.account')
            ->firstOrFail();
        $auditLog = AuditLog::query()->where('route_name', 'invoices.tax-correction')->firstOrFail();

        expect((float) $invoice->tax_total)->toBe(0.0)
            ->and((float) $invoice->total)->toBe(1000.0)
            ->and((float) $invoice->lines->first()->tax_rate)->toBe(0.0)
            ->and((float) $invoice->lines->first()->tax_amount)->toBe(0.0)
            ->and((float) $invoice->lines->first()->total)->toBe(1000.0)
            ->and((float) $correction->old_tax_total)->toBe(160.0)
            ->and((float) $correction->new_tax_total)->toBe(0.0)
            ->and($correction->created_by)->toBe($this->operator->id)
            ->and((float) $adjustment->totalDebit())->toBe(160.0)
            ->and((float) $adjustment->totalCredit())->toBe(160.0)
            ->and($adjustment->lines->pluck('account.code')->all())->toBe(['4431', '411'])
            ->and($auditLog->user_id)->toBe($this->operator->id)
            ->and($auditLog->metadata['platform_support_access'])->toBeTrue()
            ->and($auditLog->metadata['invoice_tax_correction_id'])->toBe($correction->id)
            ->and($auditLog->metadata['reason'])->toBe('Article configuré par erreur avec TVA.');
    });
});

it('does not expose the correction action outside the super-admin support session', function () {
    $this->actingAs($this->operator)
        ->get(route('invoices.show', $this->invoice))
        ->assertRedirect(route('platform.dashboard'));

    $this->actingAs($this->operator)
        ->patch(route('invoices.tax-correction', $this->invoice), [
            'tax_rates' => [$this->line->id => '0'],
            'reason' => 'Correction demandée.',
        ])
        ->assertRedirect(route('platform.dashboard'));

    expect(InvoiceTaxCorrection::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses direct tax correction when the invoice has a recorded payment', function () {
    $this->asCompany($this->company, function () {
        Payment::forceCreate([
            'invoice_id' => $this->invoice->id,
            'number' => 'REG-2026-00001',
            'payment_date' => today(),
            'amount' => 100,
            'currency' => 'USD',
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Recorded,
            'recorded_by' => $this->operator->id,
        ]);
    });

    $this->actingAs($this->operator)
        ->post(route('platform.companies.access', $this->company));

    $this->get(route('invoices.show', $this->invoice))
        ->assertOk()
        ->assertDontSee('name="tax_rates['.$this->line->id.']"', false)
        ->assertSee('règlement actif');

    $this->patch(route('invoices.tax-correction', $this->invoice), [
        'tax_rates' => [$this->line->id => '0'],
        'reason' => 'Correction avec règlement.',
    ])->assertSessionHasErrors('tax_rates');

    $this->asCompany($this->company, function () {
        expect(InvoiceTaxCorrection::query()->count())->toBe(0)
            ->and((float) $this->invoice->fresh()->tax_total)->toBe(160.0)
            ->and(AccountingEntry::query()->where('source_type', 'invoice_tax_correction')->exists())->toBeFalse();
    });
});

it('refuses direct tax correction when the invoice already has a credit note', function () {
    $this->asCompany($this->company, function () {
        CreditNote::forceCreate([
            'number' => 'NC-2026-00001',
            'invoice_id' => $this->invoice->id,
            'party_id' => $this->invoice->party_id,
            'status' => CreditNoteStatus::Issued,
            'issue_date' => today(),
            'currency' => 'USD',
            'reason' => 'Avoir déjà émis',
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 16,
            'total' => 116,
            'created_by' => $this->operator->id,
        ]);
    });

    $this->actingAs($this->operator)
        ->post(route('platform.companies.access', $this->company));

    $this->get(route('invoices.show', $this->invoice))
        ->assertOk()
        ->assertDontSee('name="tax_rates['.$this->line->id.']"', false)
        ->assertSee('Un avoir existe déjà');

    $this->patch(route('invoices.tax-correction', $this->invoice), [
        'tax_rates' => [$this->line->id => '0'],
        'reason' => 'Correction après avoir.',
    ])->assertSessionHasErrors('tax_rates');

    $this->asCompany($this->company, function () {
        expect(InvoiceTaxCorrection::query()->count())->toBe(0)
            ->and((float) $this->invoice->fresh()->tax_total)->toBe(160.0)
            ->and(AccountingEntry::query()->where('source_type', 'invoice_tax_correction')->exists())->toBeFalse();
    });
});

it('rolls back a tax correction when the current accounting period is closed', function () {
    $this->asCompany($this->company, function () {
        AccountingPeriod::forceCreate([
            'name' => 'Période verrouillée',
            'starts_on' => today()->startOfMonth(),
            'ends_on' => today()->endOfMonth(),
            'status' => PeriodStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $this->operator->id,
        ]);
    });

    $this->actingAs($this->operator)
        ->post(route('platform.companies.access', $this->company));

    $this->patch(route('invoices.tax-correction', $this->invoice), [
        'tax_rates' => [$this->line->id => '0'],
        'reason' => 'Correction en période verrouillée.',
    ])->assertStatus(409);

    $this->asCompany($this->company, function () {
        expect(InvoiceTaxCorrection::query()->count())->toBe(0)
            ->and((float) $this->invoice->fresh()->tax_total)->toBe(160.0)
            ->and(AccountingEntry::query()->where('source_type', 'invoice_tax_correction')->exists())->toBeFalse();
    });
});
