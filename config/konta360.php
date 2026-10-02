<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Root administrator account
    |--------------------------------------------------------------------------
    |
    | Seeded by AdminUserSeeder and granted the Administrateur role. The
    | defaults below are for local development only — set ADMIN_EMAIL and
    | ADMIN_PASSWORD in .env before seeding any shared or production database.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrateur Konta360'),
        'email' => env('ADMIN_EMAIL', 'admin@konta360.local'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
