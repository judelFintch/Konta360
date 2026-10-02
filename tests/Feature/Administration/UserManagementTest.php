<?php

use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Administrateur->value);
});

it('creates an active verified user with the selected role', function () {
    $this->actingAs($this->admin)->post(route('administration.users.store'), [
        'name' => 'Nouvelle Comptable',
        'email' => 'comptable@example.test',
        'password' => 'mot-de-passe',
        'password_confirmation' => 'mot-de-passe',
        'role' => Role::Comptable->value,
    ])->assertRedirect(route('administration.users.index'));

    $user = User::where('email', 'comptable@example.test')->firstOrFail();

    expect($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole(Role::Comptable->value))->toBeTrue()
        ->and(Hash::check('mot-de-passe', $user->password))->toBeTrue();
});

it('updates role status and password', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Commercial->value);

    $this->actingAs($this->admin)->put(route('administration.users.update', $user), [
        'name' => 'Direction',
        'email' => $user->email,
        'role' => Role::Direction->value,
        'is_active' => 0,
        'password' => 'nouveau-secret',
        'password_confirmation' => 'nouveau-secret',
    ])->assertRedirect(route('administration.users.index'));

    expect($user->refresh()->is_active)->toBeFalse()
        ->and($user->hasRole(Role::Direction->value))->toBeTrue()
        ->and($user->hasRole(Role::Commercial->value))->toBeFalse()
        ->and(Hash::check('nouveau-secret', $user->password))->toBeTrue();
});

it('prevents an administrator from disabling or demoting their own account', function () {
    $this->actingAs($this->admin)->put(route('administration.users.update', $this->admin), [
        'name' => $this->admin->name,
        'email' => $this->admin->email,
        'role' => Role::Commercial->value,
        'is_active' => 0,
        'password' => '',
        'password_confirmation' => '',
    ])->assertSessionHasErrors('is_active');

    expect($this->admin->refresh()->is_active)->toBeTrue()
        ->and($this->admin->hasRole(Role::Administrateur->value))->toBeTrue();
});

it('blocks login for an inactive user', function () {
    $user = User::factory()->create(['is_active' => false]);

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors('form.email')
        ->assertNoRedirect();

    $this->assertGuest();
});

it('protects user administration permissions', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('administration.users.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('administration.users.index'))->assertOk();
});
