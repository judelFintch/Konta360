<?php

namespace App\Modules\Administration\Database\Seeders;

use App\Models\User;
use App\Modules\Administration\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;

/**
 * Seeds the root administrator account (config('konta360.admin')) and grants it
 * the Administrateur role. Must run after RolesAndPermissionsSeeder.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('konta360.admin');

        $user = User::firstOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole(RoleEnum::Administrateur->value);
    }
}
