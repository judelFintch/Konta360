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

    /*
    |--------------------------------------------------------------------------
    | Subscriptions (ADR 0003)
    |--------------------------------------------------------------------------
    |
    | Payments are made outside the application (mobile money, bank
    | transfer) and confirmed by a platform administrator. The instructions
    | below are shown to companies on their subscription page.
    |
    */

    'billing' => [
        // Length of the free evaluation plan every new company starts on.
        'trial_days' => (int) env('BILLING_TRIAL_DAYS', 30),
        'payment_instructions' => [
            'mobile_money' => env('BILLING_MOBILE_MONEY'),
            'bank_transfer' => env('BILLING_BANK_ACCOUNT'),
            'contact' => env('BILLING_CONTACT_EMAIL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Terms of service and data retention
    |--------------------------------------------------------------------------
    |
    | Bump terms_version whenever the terms of service or the privacy policy
    | change: company administrators are then asked to accept them again.
    |
    | retention_years: accounting records must be kept ten years under the
    | OHADA uniform act on accounting law; a closed company's data cannot be
    | purged before then. To be confirmed by a lawyer.
    |
    */

    'terms_version' => env('TERMS_VERSION', '2026-10-06'),

    'retention_years' => (int) env('DATA_RETENTION_YEARS', 10),

];
