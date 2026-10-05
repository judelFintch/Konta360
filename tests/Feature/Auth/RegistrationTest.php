<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\User;
use App\Modules\Administration\Enums\Role;
use App\Modules\Companies\Services\CompanyProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.register');
});

test('registering creates a provisioned company and its administrator', function () {
    Event::fake([Registered::class]);

    $component = Volt::test('pages.auth.register')
        ->set('company_name', 'Kivu Services SARL')
        ->set('default_currency', 'USD')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    $component->call('register');

    $component->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();
    Event::assertDispatched(Registered::class);

    $user = User::where('email', 'test@example.com')->firstOrFail();
    $company = $user->company;

    expect($company->name)->toBe('Kivu Services SARL')
        ->and($company->default_currency)->toBe('USD')
        ->and($company->id)->not->toBe(Company::query()->oldest('id')->value('id'))
        ->and($user->hasRole(Role::Administrateur->value))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    $this->asCompany($company, function () {
        expect(Account::count())->toBe(count(CompanyProvisioner::ACCOUNTS))
            ->and(Journal::count())->toBe(count(CompanyProvisioner::JOURNALS));
    });
});

test('a new administrator must verify their email before working', function () {
    Volt::test('pages.auth.register')
        ->set('company_name', 'Kivu Services SARL')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('registration requires a company name', function () {
    Volt::test('pages.auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors(['company_name' => 'required']);

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse()
        ->and(Company::count())->toBe(1);
});
