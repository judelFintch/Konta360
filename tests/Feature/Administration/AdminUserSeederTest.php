<?php

use App\Models\User;
use App\Modules\Administration\Database\Seeders\AdminUserSeeder;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role as RoleEnum;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates the configured root admin with a usable password and the Administrateur role', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', config('konta360.admin.email'))->firstOrFail();

    expect($admin->name)->toBe(config('konta360.admin.name'))
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check(config('konta360.admin.password'), $admin->password))->toBeTrue()
        ->and($admin->hasRole(RoleEnum::Administrateur->value))->toBeTrue();
});

it('is idempotent and does not duplicate the admin user when run twice', function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::where('email', config('konta360.admin.email'))->count())->toBe(1);
});
