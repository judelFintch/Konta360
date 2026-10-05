<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Companies\Enums\SequenceType;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Administrateur->value);
});

it('allows an administrator to update company settings', function () {
    $this->actingAs($this->admin)->put(route('administration.company.update'), [
        'name' => 'Kivu Services SARL',
        'legal_form' => 'SARL',
        'tax_identifier' => 'A1234567',
        'trade_register' => 'CD/LSH/RCCM/26-B-100',
        'address' => '12, avenue du Commerce',
        'email' => 'contact@kivu.test',
        'phone' => '+243 999 000 000',
        'website' => 'https://kivu.test',
        'default_currency' => 'USD',
        'default_tax_rate' => 16,
        'default_payment_days' => 15,
        'default_quote_validity_days' => 20,
        'quote_prefix' => 'DV',
        'invoice_prefix' => 'FT',
        'credit_note_prefix' => 'NC',
        'number_padding' => 6,
        'invoice_footer' => 'Merci pour votre confiance.',
    ])->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(Company::current()->refresh())
        ->name->toBe('Kivu Services SARL')
        ->default_currency->toBe('USD')
        ->invoice_footer->toBe('Merci pour votre confiance.');
});

it('stores company branding assets on the public disk', function () {
    Storage::fake('public');

    $data = Company::current()->only([
        'name', 'legal_form', 'tax_identifier', 'trade_register', 'address', 'email',
        'phone', 'website', 'default_currency', 'invoice_footer', 'default_tax_rate',
        'default_payment_days', 'default_quote_validity_days', 'quote_prefix',
        'invoice_prefix', 'credit_note_prefix', 'number_padding',
    ]);
    $data['logo'] = UploadedFile::fake()->image('logo.png');
    $data['signature'] = UploadedFile::fake()->image('signature.png');
    $data['stamp'] = UploadedFile::fake()->image('cachet.png');

    $this->actingAs($this->admin)->put(route('administration.company.update'), $data)
        ->assertSessionHasNoErrors();

    $company = Company::current()->refresh();
    Storage::disk('public')->assertExists($company->logo_path);
    Storage::disk('public')->assertExists($company->signature_path);
    Storage::disk('public')->assertExists($company->stamp_path);
    $this->get(route('administration.company.asset', 'logo'))->assertOk();
});

it('builds configurable document numbers', function () {
    Company::current()->forceFill([
        'quote_prefix' => 'OFF',
        'invoice_prefix' => 'INV',
        'credit_note_prefix' => 'CREDIT',
        'number_padding' => 7,
    ]);
    $year = today()->format('Y');

    DB::transaction(function () use ($year) {
        expect(SequenceType::Quote->nextNumber(today()))->toBe("OFF-{$year}-0000001")
            ->and(SequenceType::Invoice->nextNumber(today()))->toBe("INV-{$year}-0000001")
            ->and(SequenceType::Invoice->nextNumber(today()))->toBe("INV-{$year}-0000002")
            ->and(SequenceType::CreditNote->nextNumber(today()))->toBe("CREDIT-{$year}-0000001");
    });
});

it('protects company settings with the proper permission', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('administration.company.edit'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('administration.company.edit'))->assertOk();
});

it('prints company legal details on commercial documents', function () {
    Company::query()->update([
        'name' => 'Konta Test SARL',
        'tax_identifier' => 'IMP-2026-001',
        'trade_register' => 'RCCM-LSH-001',
        'invoice_footer' => 'Paiement à réception.',
    ]);

    $party = Party::query()->create([
        'type' => PartyType::Customer,
        'name' => 'Client Test',
        'is_active' => true,
    ]);
    $invoice = Invoice::query()->create([
        'number' => 'FAC-TEST-001',
        'party_id' => $party->id,
        'status' => InvoiceStatus::Validated,
        'issue_date' => today(),
        'due_date' => today()->addDays(30),
        'currency' => 'CDF',
        'subtotal' => 100,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 100,
        'created_by' => $this->admin->id,
    ]);
    $invoice->lines()->create([
        'position' => 1,
        'sku' => 'SERV-001',
        'description' => 'Prestation',
        'unit' => 'service',
        'quantity' => 1,
        'unit_price' => 100,
        'discount_rate' => 0,
        'tax_rate' => 0,
        'subtotal' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    $this->actingAs($this->admin)->get(route('invoices.print', $invoice))
        ->assertOk()
        ->assertSee('Konta Test SARL')
        ->assertSee('IMP-2026-001')
        ->assertSee('RCCM-LSH-001')
        ->assertSee('Paiement à réception.');
});
