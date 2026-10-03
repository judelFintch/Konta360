<?php

use App\Models\CatalogItem;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Catalog\Enums\Unit;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->item = fn (array $overrides = []) => [
        'type' => ItemType::Service->value,
        'sku' => 'LOC-BULL',
        'name' => 'Location bulldozer',
        'unit_price' => 75,
        'currency' => 'USD',
        'tax_rate' => 16,
        'is_active' => 1,
        ...$overrides,
    ];
});

it('offers the standard units in a list on the catalog form', function () {
    $this->actingAs($this->user)
        ->get(route('catalog.create'))
        ->assertOk()
        ->assertSee('<select id="unit" name="unit"', false)
        ->assertSee('Heure (h)')
        ->assertSee('Autre…');
});

it('stores the code of a unit chosen from the list', function () {
    $this->actingAs($this->user)
        ->post(route('catalog.store'), ($this->item)(['unit' => 'hour']))
        ->assertSessionHasNoErrors();

    expect(CatalogItem::firstOrFail()->unit)->toBe('hour');
});

it('stores the text typed when « Autre » is chosen', function () {
    $this->actingAs($this->user)
        ->post(route('catalog.store'), ($this->item)(['unit' => Unit::OTHER, 'unit_other' => '  sac de 50 kg ']))
        ->assertSessionHasNoErrors();

    expect(CatalogItem::firstOrFail()->unit)->toBe('sac de 50 kg');
});

it('requires the text of a unit outside the list', function () {
    $this->actingAs($this->user)
        ->post(route('catalog.store'), ($this->item)(['unit' => Unit::OTHER, 'unit_other' => '']))
        ->assertSessionHasErrors('unit');

    expect(CatalogItem::count())->toBe(0);
});

it('reopens a unit outside the list in the free text field', function () {
    $item = CatalogItem::create([...($this->item)(), 'unit' => 'sac', 'type' => ItemType::Service]);

    $this->actingAs($this->user)
        ->get(route('catalog.edit', $item))
        ->assertOk()
        ->assertSee('value="sac"', false)
        ->assertSee('<option value="other" selected', false);
});

it('prints standard units in the document language and other units as typed', function () {
    expect(Unit::display('hour'))->toBe('h')
        ->and(Unit::display('day'))->toBe('jour')
        ->and(Unit::display('day', 'en'))->toBe('day')
        ->and(Unit::display('flat_rate', 'en'))->toBe('lump sum')
        ->and(Unit::display('sac de 50 kg', 'en'))->toBe('sac de 50 kg');
});

it('maps the units typed by hand to the standard codes', function () {
    $ids = collect(['Heures', 'jour', ' m² ', 'pièce', 'sac'])->map(fn (string $unit, int $index) => CatalogItem::create([
        ...($this->item)(), 'sku' => "ITEM-{$index}", 'unit' => $unit, 'type' => ItemType::Service,
    ])->id);

    $migration = require database_path('migrations/2026_10_03_120000_normalize_catalog_units.php');
    $migration->up();

    expect(CatalogItem::whereIn('id', $ids)->orderBy('id')->pluck('unit')->all())
        ->toBe(['hour', 'day', 'm2', 'piece', 'sac']);
});
