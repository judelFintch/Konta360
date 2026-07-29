<?php

use App\Models\Party;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Parties\Enums\PartyType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets an authorized user list parties', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(Role::Comptable->value);
    Party::create([
        'type' => PartyType::Customer,
        'name' => 'Entreprise Kivu',
        'email' => 'contact@kivu.test',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('parties.index'))
        ->assertOk()
        ->assertSee('Entreprise Kivu');
});

it('forbids a user without the parties permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(Role::Direction->value);

    $this->actingAs($user)
        ->get(route('parties.index'))
        ->assertForbidden();
});

it('creates a party with validated data', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(Role::Commercial->value);

    $this->actingAs($user)
        ->post(route('parties.store'), [
            'type' => PartyType::Customer->value,
            'name' => 'Client Lubumbashi',
            'tax_identifier' => 'NIF-001',
            'email' => 'client@example.com',
            'phone' => '+243 999 000 001',
            'address' => 'Lubumbashi',
            'is_active' => '1',
        ])
        ->assertRedirect(route('parties.index'));

    $this->assertDatabaseHas('parties', [
        'name' => 'Client Lubumbashi',
        'type' => PartyType::Customer->value,
        'is_active' => true,
    ]);
});

it('updates a party and keeps tax identifiers unique', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(Role::Administrateur->value);
    $party = Party::create([
        'type' => PartyType::Supplier,
        'name' => 'Ancien nom',
        'tax_identifier' => 'NIF-002',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->put(route('parties.update', $party), [
            'type' => PartyType::Both->value,
            'name' => 'Nouveau nom',
            'tax_identifier' => 'NIF-002',
            'is_active' => '0',
        ])
        ->assertRedirect(route('parties.index'));

    expect($party->refresh())
        ->name->toBe('Nouveau nom')
        ->type->toBe(PartyType::Both)
        ->is_active->toBeFalse();
});

it('validates required party fields', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(Role::Comptable->value);

    $this->actingAs($user)
        ->post(route('parties.store'), [
            'type' => 'invalid',
            'name' => '',
        ])
        ->assertSessionHasErrors(['type', 'name']);
});
