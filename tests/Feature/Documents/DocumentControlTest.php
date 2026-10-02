<?php

use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Documents\Services\CommercialDocumentPresenter;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Parties\Enums\PartyType;
use chillerlan\QRCode\QRCode;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    CompanySetting::query()->updateOrCreate(['id' => 1], [
        'name' => 'Société Démo SARL',
        'tax_identifier' => 'A1234567B',
        'trade_register' => 'CD/KIN/RCCM/24-B-0001',
        'address' => '12 avenue du Commerce, Kinshasa',
        'default_currency' => 'USD',
    ]);
    $party = Party::create(['type' => PartyType::Customer, 'name' => 'Client Contrôle', 'is_active' => true]);
    $this->invoice = Invoice::create([
        'number' => 'FAC-2026-00042',
        'party_id' => $party->id,
        'status' => InvoiceStatus::Validated,
        'issue_date' => '2026-09-15',
        'due_date' => '2026-10-15',
        'currency' => 'USD',
        'subtotal' => 1000,
        'discount_total' => 0,
        'tax_total' => 160,
        'total' => 1160,
        'created_by' => $this->user->id,
    ]);
    $this->invoice->lines()->create([
        'position' => 1, 'sku' => 'SRV-01', 'description' => 'Audit comptable', 'unit' => 'forfait',
        'quantity' => 1, 'unit_price' => 1000, 'discount_rate' => 0, 'tax_rate' => 16,
        'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 160, 'total' => 1160,
    ]);
    $this->presenter = app(CommercialDocumentPresenter::class);
});

it('prints the company identity and control elements on the invoice', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.print', $this->invoice))
        ->assertOk()
        ->assertSee('Société Démo SARL')
        ->assertSee('CD/KIN/RCCM/24-B-0001')
        ->assertSee($this->presenter->fingerprint($this->invoice))
        ->assertSee('Mille cent soixante dollars américains')
        ->assertSee('Net à payer')
        ->assertDontSee('Informations de l’entreprise incomplètes');
});

it('changes the control code when the document content changes', function () {
    $before = $this->presenter->fingerprint($this->invoice);

    $this->invoice->update(['total' => 1161]);

    expect($this->presenter->fingerprint($this->invoice->fresh()))->not->toBe($before);
});

it('lets anyone verify a document through its signed link', function () {
    $this->get($this->presenter->verificationUrl($this->invoice))
        ->assertOk()
        ->assertSee('Document authentique')
        ->assertSee('FAC-2026-00042')
        ->assertSee('1 160,00 USD')
        ->assertSee($this->presenter->fingerprint($this->invoice));
});

it('rejects tampered verification links', function () {
    $url = $this->presenter->verificationUrl($this->invoice);

    $this->get(str_replace('/invoice/'.$this->invoice->id.'/', '/invoice/'.($this->invoice->id + 1).'/', $url))
        ->assertForbidden();
    $this->get(substr($url, 0, -1).'0')->assertForbidden();
});

it('verifies links whatever the scheme or host they are opened with', function () {
    $path = parse_url($this->presenter->verificationUrl($this->invoice), PHP_URL_PATH);

    $this->get('https://www.autre-domaine.test'.$path)->assertOk()->assertSee('Document authentique');
    expect(strlen($path))->toBeLessThan(60);
});

it('flags cancelled invoices as not valid', function () {
    $this->invoice->update(['status' => InvoiceStatus::Cancelled]);

    $this->get($this->presenter->verificationUrl($this->invoice))
        ->assertOk()
        ->assertSee('Document authentique mais non valable');
});

it('gives draft invoices no verification code and a watermark', function () {
    $this->invoice->update(['status' => InvoiceStatus::Draft, 'number' => null]);

    $this->actingAs($this->user)
        ->get(route('invoices.print', $this->invoice))
        ->assertOk()
        ->assertSee('Document provisoire')
        ->assertSee('class="watermark"', false)
        ->assertDontSee('Code de contrôle');
});

it('spells amounts in french words', function (float $amount, string $currency, string $expected) {
    expect($this->presenter->amountInWords($amount, $currency))->toBe($expected);
})->with([
    [1, 'USD', 'Un dollar américain'],
    [1160, 'USD', 'Mille cent soixante dollars américains'],
    [2500.5, 'CDF', 'Deux mille cinq cents francs congolais et cinquante centimes'],
    [1000000, 'CDF', 'Un million de francs congolais'],
]);

it('warns when the company identity is incomplete', function () {
    CompanySetting::query()->update(['tax_identifier' => null]);

    $this->actingAs($this->user)
        ->get(route('invoices.print', $this->invoice))
        ->assertSee('Informations de l’entreprise incomplètes');
});

it('shows the company name in the application header', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertSee('<title>Société Démo SARL — Konta360</title>', false);
});

it('shows the invoice situation and control code on the invoice page', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.show', $this->invoice))
        ->assertOk()
        ->assertSee('Reste à payer')
        ->assertSee('1 160,00')
        ->assertSee($this->presenter->fingerprint($this->invoice))
        ->assertSee('Facture créée');
});

it('summarises invoices by currency on the list page', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertSeeInOrder(['Total facturé', '1 160,00', 'Reste à encaisser', '1 160,00'])
        ->assertSee('À encaisser');
});

it('prints a png qr code that decodes to a working verification link', function () {
    $html = $this->actingAs($this->user)->get(route('invoices.print', $this->invoice))->getContent();

    expect(preg_match('/class="qr" src="data:image\/png;base64,([^"]+)"/', $html, $matches))->toBe(1);

    $file = tempnam(sys_get_temp_dir(), 'qr');
    file_put_contents($file, base64_decode($matches[1]));
    $decoded = (string) (new QRCode)->readFromFile($file);
    unlink($file);

    expect($decoded)->toBe($this->presenter->verificationUrl($this->invoice));
    $this->get($decoded)->assertOk()->assertSee('Document authentique');
});
