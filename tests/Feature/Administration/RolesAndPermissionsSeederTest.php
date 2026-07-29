<?php

use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Permission as PermissionEnum;
use App\Modules\Administration\Enums\Role as RoleEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates every permission declared in the Permission enum', function () {
    expect(Permission::pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionEnum::values())->sort()->values()->all());
});

it('creates exactly the four roles from the cahier des charges', function () {
    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(collect(RoleEnum::values())->sort()->values()->all());
});

it('grants the administrateur role every permission', function () {
    $role = Role::findByName(RoleEnum::Administrateur->value);

    expect($role->permissions()->count())->toBe(count(PermissionEnum::values()));
});

it('does not let the commercial role touch accounting, treasury, or settings', function () {
    $role = Role::findByName(RoleEnum::Commercial->value);
    $permissionNames = $role->permissions()->pluck('name')->all();

    expect($permissionNames)
        ->toContain(PermissionEnum::InvoicesCreate->value)
        ->not->toContain(PermissionEnum::AccountingEntriesPost->value)
        ->not->toContain(PermissionEnum::TreasuryManage->value)
        ->not->toContain(PermissionEnum::SettingsManage->value)
        ->not->toContain(PermissionEnum::UsersManage->value);
});

it('only lets administrateur close accounting periods', function () {
    foreach (RoleEnum::cases() as $role) {
        $model = Role::findByName($role->value);
        $hasPermission = $model->permissions()->pluck('name')->contains(PermissionEnum::AccountingPeriodsClose->value);

        expect($hasPermission)->toBe($role === RoleEnum::Administrateur);
    }
});

it('lets direction view financial statements and post entries but not create invoices', function () {
    $role = Role::findByName(RoleEnum::Direction->value);
    $permissionNames = $role->permissions()->pluck('name')->all();

    expect($permissionNames)
        ->toContain(PermissionEnum::FinancialStatementsView->value)
        ->toContain(PermissionEnum::AccountingEntriesPost->value)
        ->not->toContain(PermissionEnum::InvoicesCreate->value);
});

it('is idempotent when run twice', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(count(RoleEnum::values()))
        ->and(Permission::count())->toBe(count(PermissionEnum::values()));
});

it('assigns a real user a role and lets gates authorize against permissions', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::Comptable->value);

    expect($user->hasPermissionTo(PermissionEnum::InvoicesValidate->value))->toBeTrue()
        ->and($user->hasPermissionTo(PermissionEnum::UsersManage->value))->toBeFalse();
});
