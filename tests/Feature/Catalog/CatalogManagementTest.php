<?php

use App\Models\CatalogItem;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Catalog\Enums\ItemType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets an authorized user list catalog items', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Comptable->value);
    CatalogItem::create([
        'type' => ItemType::Service,
        'sku' => 'SRV-001',
        'name' => 'Tenue comptable',
        'unit' => 'mois',
        'unit_price' => 500,
        'currency' => 'USD',
        'tax_rate' => 16,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('catalog.index'))
        ->assertOk()
        ->assertSee('Tenue comptable')
        ->assertSee('SRV-001');
});

it('forbids roles without catalog management permission', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Commercial->value);

    $this->actingAs($user)
        ->get(route('catalog.index'))
        ->assertForbidden();
});

it('creates a normalized catalog item', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Administrateur->value);

    $this->actingAs($user)
        ->post(route('catalog.store'), [
            'type' => ItemType::Product->value,
            'sku' => ' prod-001 ',
            'name' => 'Ramette papier',
            'unit' => 'carton',
            'unit_price' => '24.50',
            'currency' => 'USD',
            'tax_rate' => '16',
            'is_active' => '1',
        ])
        ->assertRedirect(route('catalog.index'));

    $this->assertDatabaseHas('catalog_items', [
        'sku' => 'PROD-001',
        'name' => 'Ramette papier',
        'currency' => 'USD',
    ]);
});

it('updates an item while retaining its unique sku', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Comptable->value);
    $item = CatalogItem::create([
        'type' => ItemType::Product,
        'sku' => 'PROD-002',
        'name' => 'Ancien article',
        'unit' => 'pièce',
        'unit_price' => 10,
        'currency' => 'CDF',
        'tax_rate' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->put(route('catalog.update', $item), [
            'type' => ItemType::Service->value,
            'sku' => 'PROD-002',
            'name' => 'Article actualisé',
            'unit' => 'heure',
            'unit_price' => '15.75',
            'currency' => 'USD',
            'tax_rate' => '16',
            'is_active' => '0',
        ])
        ->assertRedirect(route('catalog.index'));

    expect($item->refresh())
        ->name->toBe('Article actualisé')
        ->type->toBe(ItemType::Service)
        ->unit_price->toBe('15.75')
        ->is_active->toBeFalse();
});

it('rejects invalid prices currencies and tax rates', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Comptable->value);

    $this->actingAs($user)
        ->post(route('catalog.store'), [
            'type' => ItemType::Service->value,
            'sku' => '',
            'name' => '',
            'unit' => '',
            'unit_price' => '-1',
            'currency' => 'EUR',
            'tax_rate' => '101',
        ])
        ->assertSessionHasErrors(['sku', 'name', 'unit', 'unit_price', 'currency', 'tax_rate']);
});
