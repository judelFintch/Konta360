<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Party;
use App\Models\PlatformEvent;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Companies\Services\PlatformCompanyAccess;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->operator = User::factory()->platformAdmin()->create([
        'name' => 'Super-admin assistance',
        'email_verified_at' => now(),
    ]);

    $this->company = Company::factory()->onPlan('pro')->create(['name' => 'Société assistée']);
    $this->companyAdmin = User::factory()->forCompany($this->company)->create(['email' => 'admin@assistee.test']);
    $this->companyAdmin->assignRole(Role::Administrateur->value);

    $this->otherCompany = Company::factory()->onPlan('pro')->create(['name' => 'Autre société']);
    $this->otherAdmin = User::factory()->forCompany($this->otherCompany)->create(['email' => 'admin@autre.test']);
    $this->otherAdmin->assignRole(Role::Administrateur->value);

    $this->asCompany($this->company, fn () => Party::forceCreate([
        'type' => 'customer',
        'name' => 'Client société assistée',
        'is_active' => true,
    ]));
    $this->asCompany($this->otherCompany, fn () => Party::forceCreate([
        'type' => 'customer',
        'name' => 'Client autre société',
        'is_active' => true,
    ]));
});

it('keeps platform administrators out of company data until they start support access', function () {
    $this->actingAs($this->operator)
        ->get(route('parties.index'))
        ->assertRedirect(route('platform.dashboard'));
});

it('does not let company users start support access', function () {
    $this->actingAs($this->companyAdmin)
        ->post(route('platform.companies.access', $this->otherCompany))
        ->assertForbidden();
});

it('grants full company permissions only in the selected company and audits writes as the super-admin', function () {
    $this->actingAs($this->operator)
        ->post(route('platform.companies.access', $this->company))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(PlatformCompanyAccess::SESSION_KEY, $this->company->id);

    $this->get(route('accounting.entries.create'))->assertOk();
    $this->get(route('administration.users.index'))
        ->assertOk()
        ->assertSee('admin@assistee.test')
        ->assertDontSee('admin@autre.test');

    $this->get(route('parties.index'))
        ->assertOk()
        ->assertSee('Client société assistée')
        ->assertDontSee('Client autre société');

    $this->post(route('parties.store'), [
        'type' => 'customer',
        'name' => 'Client créé en assistance',
        'is_active' => true,
    ])->assertRedirect(route('parties.index'));

    $auditLog = AuditLog::query()
        ->withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('route_name', 'parties.store')
        ->firstOrFail();
    expect($auditLog->user_id)->toBe($this->operator->id)
        ->and($auditLog->company_id)->toBe($this->company->id)
        ->and($auditLog->metadata['platform_support_access'])->toBeTrue()
        ->and($auditLog->metadata['platform_actor_id'])->toBe($this->operator->id);

    expect(PlatformEvent::query()
        ->where('company_id', $this->company->id)
        ->where('actor_id', $this->operator->id)
        ->where('action', 'support_access_started')
        ->exists())->toBeTrue();
});

it('ends support access and returns the super-admin to the platform', function () {
    $this->actingAs($this->operator)
        ->post(route('platform.companies.access', $this->company))
        ->assertRedirect(route('dashboard'));

    $this->post(route('platform.company-access.exit'))
        ->assertRedirect(route('platform.dashboard'))
        ->assertSessionMissing(PlatformCompanyAccess::SESSION_KEY);

    $this->get(route('dashboard'))->assertRedirect(route('platform.dashboard'));
    expect(PlatformEvent::query()
        ->where('company_id', $this->company->id)
        ->where('actor_id', $this->operator->id)
        ->where('action', 'support_access_ended')
        ->exists())->toBeTrue();
});

it('keeps expired companies read-only and refuses suspended or closed companies', function () {
    $expired = Company::factory()->expired()->create(['name' => 'Société expirée']);
    $suspended = Company::factory()->create(['name' => 'Société suspendue', 'suspended_at' => now()]);
    $closed = Company::factory()->create(['name' => 'Société clôturée']);
    $closed->forceFill(['closed_at' => now()])->save();

    $this->actingAs($this->operator)
        ->post(route('platform.companies.access', $expired))
        ->assertRedirect(route('dashboard'));

    $this->get(route('parties.index'))->assertOk();
    $this->post(route('parties.store'), [
        'type' => 'customer',
        'name' => 'Écriture interdite',
        'is_active' => true,
    ])->assertRedirect(route('subscription.show'));

    expect(PlatformEvent::query()->where('company_id', $expired->id)->where('action', 'support_access_started')->exists())->toBeTrue();

    $this->post(route('platform.company-access.exit'))
        ->assertRedirect(route('platform.dashboard'))
        ->assertSessionMissing(PlatformCompanyAccess::SESSION_KEY);

    foreach ([$suspended, $closed] as $company) {
        $this->post(route('platform.companies.access', $company))
            ->assertRedirect(route('platform.companies.show', $company))
            ->assertSessionHasErrors('support_access')
            ->assertSessionMissing(PlatformCompanyAccess::SESSION_KEY);
    }
});
