<?php

namespace App\Modules\Administration\Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Modules\Administration\Enums\Role as RoleEnum;
use App\Modules\Companies\Services\CompanyProvisioner;
use Illuminate\Database\Seeder;

/**
 * Seeds the root administrator account (config('konta360.admin')) of the
 * first company and grants it the Administrateur role. Must run after
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
            $user->company()->associate($company);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->assignRole(RoleEnum::Administrateur->value);
    }
}
