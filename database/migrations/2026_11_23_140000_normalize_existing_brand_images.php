<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Logos, signatures and stamps uploaded before they were cleaned on upload
 * are cleaned once, at deployment. Images that cannot be processed are left
 * untouched; the command can also be run by hand later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('konta360:normalize-brand-images');
    }

    public function down(): void
    {
        // The original files are not kept: nothing to restore.
    }
};
