<?php

use App\Models\FixedAsset;
use App\Models\Party;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\FixedAssets\Enums\AssetCategory;
use App\Modules\FixedAssets\Enums\AssetStatus;
use App\Modules\FixedAssets\Services\DepreciationSchedule;
use App\Modules\Parties\Enums\PartyType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->supplier = Party::create(['type' => PartyType::Supplier, 'name' => 'Fournisseur Actif', 'is_active' => true]);
});

it('creates and numbers a fixed asset', function () {
    $this->actingAs($this->user)
        ->post(route('fixed-assets.store'), [
            'name' => 'Serveur principal',
            'category' => AssetCategory::Computer->value,
            'acquisition_date' => '2026-01-15',
            'in_service_date' => '2026-02-01',
            'acquisition_cost' => '12000',
            'residual_value' => '0',
            'currency' => 'USD',
            'useful_life_months' => 60,
            'supplier_id' => $this->supplier->id,
        ])
        ->assertRedirect();

    expect(FixedAsset::firstOrFail())
        ->code->toMatch('/^IMM-2026-\d{5}$/')
        ->status->toBe(AssetStatus::Active)
        ->category->toBe(AssetCategory::Computer)
        ->created_by->toBe($this->user->id);
});

it('calculates a straight-line schedule with an exact final adjustment', function () {
    $asset = FixedAsset::create([
        'code' => 'IMM-2026-00001',
        'name' => 'Machine',
        'category' => AssetCategory::Equipment,
        'acquisition_date' => '2026-01-01',
        'in_service_date' => '2026-01-01',
        'acquisition_cost' => 1000,
        'residual_value' => 100,
        'currency' => 'USD',
        'useful_life_months' => 7,
        'status' => AssetStatus::Active,
        'created_by' => $this->user->id,
    ]);

    $schedule = app(DepreciationSchedule::class)->for($asset);

    expect($schedule)->toHaveCount(7)
        ->and(round($schedule->sum('depreciation'), 2))->toBe(900.0)
        ->and($schedule->last()['accumulated'])->toBe(900.0)
        ->and($schedule->last()['net_book_value'])->toBe(100.0);
});

it('rejects a residual value greater than or equal to acquisition cost', function () {
    $this->actingAs($this->user)
        ->post(route('fixed-assets.store'), [
            'name' => 'Bien invalide',
            'category' => AssetCategory::Other->value,
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'acquisition_cost' => 100,
            'residual_value' => 100,
            'currency' => 'CDF',
            'useful_life_months' => 12,
        ])
        ->assertSessionHasErrors('residual_value');

    expect(FixedAsset::count())->toBe(0);
});

it('updates an asset and marks it as disposed', function () {
    $asset = FixedAsset::create([
        'code' => 'IMM-2026-00002',
        'name' => 'Ancien véhicule',
        'category' => AssetCategory::Vehicle,
        'acquisition_date' => '2025-01-01',
        'in_service_date' => '2025-01-01',
        'acquisition_cost' => 20000,
        'residual_value' => 2000,
        'currency' => 'USD',
        'useful_life_months' => 60,
        'status' => AssetStatus::Active,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->put(route('fixed-assets.update', $asset), [
            'name' => 'Véhicule sorti',
            'category' => AssetCategory::Vehicle->value,
            'acquisition_date' => '2025-01-01',
            'in_service_date' => '2025-01-01',
            'acquisition_cost' => 20000,
            'residual_value' => 2000,
            'currency' => 'USD',
            'useful_life_months' => 60,
            'status' => AssetStatus::Disposed->value,
        ])
        ->assertRedirect(route('fixed-assets.show', $asset));

    expect($asset->refresh())
        ->name->toBe('Véhicule sorti')
        ->status->toBe(AssetStatus::Disposed);
});

it('protects fixed assets with the dedicated permission', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('fixed-assets.index'))->assertForbidden();
    $this->actingAs($this->user)->get(route('fixed-assets.index'))->assertOk();
});
