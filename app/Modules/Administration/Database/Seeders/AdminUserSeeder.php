<?php

namespace App\Modules\Administration\Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Modules\Administration\Enums\Role as RoleEnum;
use App\Modules\Companies\Services\CompanyProvisioner;
use Illuminate\Database\Seeder;

/**
 * Seeds the platform administrator with a temporary password and grants
 * it the Administrateur role. Must run after
 * RolesAndPermissionsSeeder.
 */
class AdminUserSeeder extends Seeder
{
    public function run(CompanyProvisioner $provisioner): void
    {
        $admin = config('konta360.admin');

        $company = Company::query()->oldest('id')->first() ?? Company::create(['name' => config('app.name', 'Konta360')]);
        $provisioner->provision($company);

        $user = User::firstOrNew(
            ['email' => $admin['email']],
            ['name' => $admin['name'], 'password' => $admin['password']]
        );
        if (! $user->exists) {
            $user->forceFill(['email_verified_at' => now(), 'is_platform_admin' => true, 'must_change_password' => true])->save();
        }

        $user->assignRole(RoleEnum::Administrateur->value);
    }
}
