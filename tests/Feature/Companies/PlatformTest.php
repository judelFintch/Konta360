<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Documents\Services\CommercialDocumentPresenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->operator = User::factory()->platformAdmin()->create();
    $this->company = Company::factory()->create(['name' => 'Société Bêta']);
    $this->admin = User::factory()->forCompany($this->company)->create();
    $this->admin->assignRole(Role::Administrateur->value);
});

it('sends platform administrators to the platform area only', function () {
    $this->actingAs($this->operator)->get(route('dashboard'))->assertRedirect(route('platform.dashboard'));
    $this->actingAs($this->operator)->get(route('invoices.index'))->assertRedirect(route('platform.dashboard'));

    $this->actingAs($this->operator)->get(route('platform.companies.index'))
        ->assertOk()
        ->assertSee('Société Bêta')
        ->assertSee($this->admin->email);
});

it('keeps company users out of the platform area', function () {
    $this->actingAs($this->admin)->get(route('platform.companies.index'))->assertForbidden();
    $this->actingAs($this->admin)->patch(route('platform.companies.suspend', $this->company))->assertForbidden();

    expect($this->company->refresh()->isSuspended())->toBeFalse();
});

it('refuses a user attached to no company who is not a platform administrator', function () {
    $orphan = User::factory()->create(['company_id' => null]);

    $this->actingAs($orphan)->get(route('dashboard'))->assertForbidden();
});

it('suspends a company: its users are signed out and cannot sign in again', function () {
    $this->actingAs($this->operator)->patch(route('platform.companies.suspend', $this->company))->assertRedirect();
    expect($this->company->refresh()->isSuspended())->toBeTrue();

    $this->actingAs($this->admin->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();

    Volt::test('pages.auth.login')
        ->set('form.email', $this->admin->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors('form.email');
    $this->assertGuest();
});

it('reactivates a suspended company', function () {
    $this->company->forceFill(['suspended_at' => now()])->save();

    $this->actingAs($this->operator)->patch(route('platform.companies.reactivate', $this->company))->assertRedirect();

    expect($this->company->refresh()->isSuspended())->toBeFalse();
    $this->actingAs($this->admin->fresh())->get(route('dashboard'))->assertOk();
});

it('creates a platform administrator from the command line', function () {
    $this->artisan('konta360:platform-admin', ['email' => 'Support@Konta360.test'])
        ->expectsQuestion('Mot de passe', 'un-mot-de-passe-solide')
        ->assertSuccessful();

    $operator = User::where('email', 'support@konta360.test')->firstOrFail();
    expect($operator->is_platform_admin)->toBeTrue()
        ->and($operator->company_id)->toBeNull()
        ->and($operator->hasVerifiedEmail())->toBeTrue();
});

it('refuses to turn an existing company user into a platform administrator', function () {
    $this->artisan('konta360:platform-admin', ['email' => $this->admin->email])->assertFailed();

    expect($this->admin->refresh()->is_platform_admin)->toBeFalse();
});

it('serves the issuing company’s logo on the public verification page', function () {
    Storage::fake('public');
    $this->company->forceFill(['logo_path' => UploadedFile::fake()->image('logo.png')->store('companies/'.$this->company->id, 'public')])->save();
    $invoice = $this->asCompany($this->company, function () {
        $party = Party::forceCreate(['type' => 'customer', 'name' => 'Client', 'is_active' => true]);

        return Invoice::forceCreate([
            'number' => 'FAC-BETA', 'party_id' => $party->id, 'status' => 'validated', 'issue_date' => today(),
            'due_date' => today(), 'currency' => 'USD', 'subtotal' => 1, 'discount_total' => 0, 'tax_total' => 0,
            'total' => 1, 'created_by' => $this->admin->id,
        ]);
    });
    $token = app(CommercialDocumentPresenter::class)->verificationToken('invoice', $invoice->id);

    $this->get(route('documents.verify.logo', ['type' => 'invoice', 'id' => $invoice->id, 'token' => $token]))->assertOk();
    $this->get(route('documents.verify.logo', ['type' => 'invoice', 'id' => $invoice->id, 'token' => 'faux0jeton0']))->assertForbidden();
});
