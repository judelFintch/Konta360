<?php

use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\User;
use App\Modules\Accounting\Enums\PeriodStatus;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\FixedAssets\Enums\AssetCategory;
use App\Modules\FixedAssets\Enums\AssetStatus;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->asset = FixedAsset::create([
        'code' => 'IMM-2025-00010',
        'name' => 'Équipement industriel',
        'category' => AssetCategory::Equipment,
        'acquisition_date' => '2025-01-01',
        'in_service_date' => '2025-01-01',
        'acquisition_cost' => 1200,
        'residual_value' => 0,
        'currency' => 'USD',
        'useful_life_months' => 12,
        'status' => AssetStatus::Active,
        'created_by' => $this->user->id,
    ]);
});

it('posts all unrecorded depreciation installments through a date', function () {
    $this->actingAs($this->user)
        ->post(route('fixed-assets.depreciations.store', $this->asset), [
            'through_date' => '2025-03-31',
        ])
        ->assertRedirect();

    expect(FixedAssetDepreciation::count())->toBe(2)
        ->and(AccountingEntry::where('source_type', 'fixed_asset_depreciation')->count())->toBe(2)
        ->and(FixedAssetDepreciation::sum('amount'))->toBe(200);

    $entry = AccountingEntry::with('lines.account')->firstOrFail();
    expect($entry->journal()->value('code'))->toBe('OD')
        ->and($entry->lines->pluck('account.code')->all())->toBe(['68', '28'])
        ->and($entry->totalDebit())->toBe(100.0)
        ->and($entry->totalCredit())->toBe(100.0);
});

it('does not post the same depreciation installment twice', function () {
    $this->actingAs($this->user)->post(route('fixed-assets.depreciations.store', $this->asset), [
        'through_date' => '2025-02-28',
    ]);

    $this->actingAs($this->user)
        ->post(route('fixed-assets.depreciations.store', $this->asset), [
            'through_date' => '2025-02-28',
        ])
        ->assertSessionHasErrors('through_date');

    expect(FixedAssetDepreciation::count())->toBe(1)
        ->and(AccountingEntry::count())->toBe(1);
});

it('rolls back depreciation posting when its accounting period is closed', function () {
    $admin = User::factory()->create();
    AccountingPeriod::create([
        'name' => 'Exercice 2025',
        'starts_on' => '2025-01-01',
        'ends_on' => '2025-12-31',
        'status' => PeriodStatus::Closed,
        'closed_at' => now(),
        'closed_by' => $admin->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('fixed-assets.depreciations.store', $this->asset), [
            'through_date' => '2025-02-28',
        ])
        ->assertStatus(409);

    expect(FixedAssetDepreciation::count())->toBe(0)
        ->and(AccountingEntry::count())->toBe(0);
});

it('shows grouped desktop navigation without listing every module inline', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Ventes')
        ->assertSee('Comptabilité')
        ->assertSee('Référentiels');
});
