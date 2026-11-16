<?php

use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDeductionCorrection;
use App\Models\InvoiceLine;
use App\Models\Party;
use App\Models\Payment;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Modules\Accounting\Enums\PeriodStatus;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Companies\Services\CurrentCompany;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Invoices\Services\InvoiceDeductionCorrector;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Services\PaymentRecorder;

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

        $this->fee = $this->invoice->deductions()->create([
            'position' => 1, 'type' => 'client_expense', 'description' => 'Carburant',
            'quantity' => 1, 'unit_price' => 100, 'amount' => 100,
        ]);
        $payment = app(PaymentRecorder::class)->record($this->invoice, [
            'payment_date' => today(), 'amount' => 200, 'method' => 'cash',
        ], $this->operator->id);
        $this->advance = $this->invoice->deductions()->create([
            'position' => 2, 'type' => 'advance', 'description' => 'Avance initiale',
            'quantity' => 1, 'unit_price' => 200, 'amount' => 200,
            'received_on' => today(), 'payment_method' => 'cash', 'payment_id' => $payment->id,
        ]);
        $this->invoice->update(['deductions_total' => 300]);
        app(AccountingService::class)->postInvoice($this->invoice, $this->operator->id);
    });
});

function deductionPayload($test): array
{
    return app(CurrentCompany::class)->runAs($test->company, function () use ($test) {
        return [
            'reason' => 'Erreur constatée sur le décompte.',
            'deduction_version' => app(InvoiceDeductionCorrector::class)->version($test->invoice),
            'deductions' => $test->invoice->deductions()->get()->map(fn ($item) => [
                'id' => $item->id, 'type' => $item->type->value, 'description' => $item->description,
                'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
                'received_on' => $item->received_on?->format('Y-m-d'),
                'payment_method' => $item->payment_method?->value,
                'treasury_account_id' => $item->treasury_account_id, 'reference' => $item->reference,
            ])->all(),
        ];
    });
}

it('corrects fees and advances with balanced adjustments and replacement payments', function () {
    $payload = deductionPayload($this);
    $payload['deductions'][0]['unit_price'] = 150;
    $payload['deductions'][1]['unit_price'] = 250;
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->get(route('invoices.deductions.edit', $this->invoice))->assertOk()->assertSee('deduction_version');
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $this->invoice));
    $this->asCompany($this->company, function () {
        $invoice = $this->invoice->fresh();
        $correction = InvoiceDeductionCorrection::firstOrFail();
        $entry = AccountingEntry::where('source_type', 'invoice_deduction_correction')->with('lines.account')->firstOrFail();
        expect($invoice->number)->toBe('FAC-2026-00001')
            ->and($invoice->status)->toBe(InvoiceStatus::Validated)
            ->and($invoice->total)->toBe('1160.00')
            ->and($invoice->deductions_total)->toBe('400.00')
            ->and($invoice->balanceDue())->toBe(760.0)
            ->and($invoice->advancePaidAmount())->toBe(250.0)
            ->and($this->advance->fresh()->payment_id)->not->toBe($this->advance->payment_id)
            ->and(Payment::findOrFail($this->advance->payment_id)->status)->toBe(PaymentStatus::Reversed)
            ->and(Payment::where('status', PaymentStatus::Recorded)->sum('amount'))->toEqual(250)
            ->and($entry->totalDebit())->toBe(50.0)
            ->and($entry->totalCredit())->toBe(50.0)
            ->and($entry->lines->pluck('account.code')->all())->toBe(['60', '411'])
            ->and($correction->old_deductions[0]['amount'])->toBe('100.00')
            ->and($correction->new_deductions[0]['amount'])->toBe('150.00')
            ->and(AuditLog::where('route_name', 'invoices.deduction-correction')->firstOrFail()->metadata['invoice_deduction_correction_id'])->toBe($correction->id);
    });
    $this->get(route('invoices.show', $this->invoice))->assertOk()->assertSee('Historique des corrections de frais et avances');
    $this->get(route('invoices.print', $this->invoice))->assertOk()->assertSee('760,00');
});

it('preserves payments and accounting for label-only corrections', function () {
    $payload = deductionPayload($this);
    $payload['deductions'][1]['description'] = 'Avance corrigée';
    $count = AccountingEntry::withoutGlobalScopes()->count();
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertSessionHasNoErrors();
    $this->asCompany($this->company, function () use ($count) {
        expect($this->advance->fresh()->payment_id)->toBe($this->advance->payment_id)
            ->and(AccountingEntry::count())->toBe($count)
            ->and(Payment::count())->toBe(1);
    });
});

it('supports removing all deductions and reverses corrected offsets on cancellation', function () {
    $payload = deductionPayload($this);
    $payload['deductions'] = [];
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertSessionHasNoErrors();
    $this->patch(route('invoices.cancel', $this->invoice))->assertSessionHas('success');
    $this->asCompany($this->company, function () {
        $entries = AccountingEntry::with('lines.account')->get();
        $expenseLines = $entries->flatMap->lines->where('account.code', '60');
        expect($this->invoice->fresh()->deductions_total)->toBe('0.00')
            ->and($this->invoice->deductions()->count())->toBe(0)
            ->and((float) $expenseLines->sum('debit'))->toBe((float) $expenseLines->sum('credit'))
            ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled);
    });
});

it('rejects stale, foreign, unchanged or excessive corrections atomically', function ($kind) {
    $payload = deductionPayload($this);
    if ($kind === 'stale') {
        $payload['deduction_version'] = str_repeat('0', 64);
    }
    if ($kind === 'foreign') {
        $payload['deductions'][0]['id'] = 999999;
    }
    if ($kind === 'excessive') {
        $payload['deductions'][1]['unit_price'] = 1100;
    }
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertSessionHasErrors('deductions');
    $this->asCompany($this->company, function () {
        expect(InvoiceDeductionCorrection::count())->toBe(0)
            ->and($this->advance->fresh()->payment_id)->toBe($this->advance->payment_id)
            ->and(Payment::firstOrFail()->status)->toBe(PaymentStatus::Recorded)
            ->and($this->invoice->fresh()->deductions_total)->toBe('300.00');
    });
})->with(['stale', 'foreign', 'unchanged', 'excessive']);

it('rolls back all changes if the posting period is closed', function () {
    $this->asCompany($this->company, function () {
        AccountingPeriod::forceCreate([
            'name' => 'Clôturée', 'starts_on' => today()->startOfMonth(), 'ends_on' => today()->endOfMonth(),
            'status' => PeriodStatus::Closed, 'closed_at' => now(), 'closed_by' => $this->operator->id,
        ]);
    });
    $payload = deductionPayload($this);
    $payload['deductions'][0]['unit_price'] = 150;
    $payload['deductions'][1]['unit_price'] = 250;
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertStatus(409);
    $this->asCompany($this->company, function () {
        expect(InvoiceDeductionCorrection::count())->toBe(0)
            ->and($this->fee->fresh()->amount)->toBe('100.00')
            ->and(Payment::firstOrFail()->status)->toBe(PaymentStatus::Recorded);
    });
});

it('denies ordinary administrators and cross-company support sessions', function () {
    $payload = deductionPayload($this);
    $admin = User::factory()->create(['company_id' => $this->company->id, 'email_verified_at' => now()]);
    $admin->assignRole(Role::Administrateur->value);
    $this->actingAs($admin)->get(route('invoices.deductions.edit', $this->invoice))->assertForbidden();
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertForbidden();
    $other = Company::factory()->onPlan('pro')->create();
    $this->actingAs($this->operator)->post(route('platform.companies.access', $other));
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertNotFound();
});

it('adds and removes advances with matching treasury movements', function () {
    $bank = $this->asCompany($this->company, fn () => TreasuryAccount::create([
        'name' => 'Banque USD', 'type' => 'bank', 'currency' => 'USD', 'is_active' => true,
        'created_by' => $this->operator->id,
    ]));
    $payload = deductionPayload($this);
    $payload['deductions'][] = [
        'type' => 'advance', 'description' => 'Avance oubliée', 'quantity' => 1, 'unit_price' => 80,
        'received_on' => today()->format('Y-m-d'), 'payment_method' => 'bank_transfer', 'treasury_account_id' => $bank->id,
    ];
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertSessionHasNoErrors();
    $this->asCompany($this->company, function () {
        expect($this->invoice->fresh()->advancePaidAmount())->toBe(280.0)
            ->and(TreasuryTransaction::where('source_type', 'payment')->sum('amount'))->toEqual(80);
    });
    $payload = deductionPayload($this);
    array_pop($payload['deductions']);
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertSessionHasNoErrors();
    $this->asCompany($this->company, function () {
        expect(TreasuryTransaction::where('source_type', 'payment_reversal')->sum('amount'))->toEqual(80)
            ->and($this->invoice->fresh()->advancePaidAmount())->toBe(200.0);
    });
});

it('prevents over-settlement and rolls back already reversed advances', function () {
    $this->asCompany($this->company, fn () => app(PaymentRecorder::class)->record($this->invoice, [
        'payment_date' => today(), 'amount' => 800, 'method' => 'cash',
    ], $this->operator->id));
    $payload = deductionPayload($this);
    $payload['deductions'][0]['unit_price'] = 150;
    $payload['deductions'][1]['unit_price'] = 250;
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertSessionHasErrors('deductions');
    $this->asCompany($this->company, function () {
        expect(Payment::findOrFail($this->advance->payment_id)->status)->toBe(PaymentStatus::Recorded)
            ->and(AccountingEntry::where('source_type', 'payment_reversal')->count())->toBe(0)
            ->and(InvoiceDeductionCorrection::count())->toBe(0);
    });
});

it('refuses correction of draft or cancelled invoices', function ($status) {
    $payload = deductionPayload($this);
    $this->asCompany($this->company, fn () => $this->invoice->update(['status' => $status]));
    $this->actingAs($this->operator)->post(route('platform.companies.access', $this->company));
    $this->get(route('invoices.deductions.edit', $this->invoice))->assertStatus(409);
    $this->patch(route('invoices.deduction-correction', $this->invoice), $payload)->assertStatus(409);
})->with(['draft', 'cancelled']);
