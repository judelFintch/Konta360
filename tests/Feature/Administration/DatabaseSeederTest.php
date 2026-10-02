<?php

use App\Models\User;
use App\Modules\Administration\Enums\Permission as PermissionEnum;
use App\Modules\Administration\Enums\Role as RoleEnum;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Deliberately no shared beforeEach() that pre-seeds roles/permissions: DatabaseSeeder must be
// the *first* thing to touch these tables in this file. DatabaseSeeder uses WithoutModelEvents,
// which runs every nested seeder inside Model::withoutEvents() and silently disables the "saved"
// listener spatie/laravel-permission relies on to invalidate its permission cache. A test that
// warms the cache via a normal (events-enabled) seed call first would mask that regression, which
// is exactly what happened with `php artisan migrate:fresh --seed` in development: it threw
// PermissionDoesNotExist even though the equivalent direct-seeder test passed.
it('seeds roles and permissions successfully via the real DatabaseSeeder entrypoint', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Role::count())->toBe(count(RoleEnum::values()))
        ->and(Permission::count())->toBe(count(PermissionEnum::values()));

    $admin = User::where('email', config('konta360.admin.email'))->firstOrFail();
    expect($admin->hasRole(RoleEnum::Administrateur->value))->toBeTrue()
        ->and($admin->hasPermissionTo(PermissionEnum::UsersManage->value))->toBeTrue();
});
