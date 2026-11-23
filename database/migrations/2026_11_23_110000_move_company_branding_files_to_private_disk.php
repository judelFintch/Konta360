<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Logos, signatures and stamps used to live on the public disk, where
 * anyone could download them through /storage. They now live on the
 * private disk and are only served to signed-in users (and the logo on
 * the verification page). Paths stay the same, only the disk changes.
 */
return new class extends Migration
{
    private const COLUMNS = ['logo_path', 'signature_path', 'stamp_path'];

    public function up(): void
    {
        $this->move(Storage::disk('public'), Storage::disk('local'));
    }

    public function down(): void
    {
        $this->move(Storage::disk('local'), Storage::disk('public'));
    }

    private function move($from, $to): void
    {
        DB::table('companies')->select(['id', ...self::COLUMNS])->orderBy('id')->each(function ($company) use ($from, $to) {
            foreach (self::COLUMNS as $column) {
                $path = $company->{$column};
                if (! $path || ! $from->exists($path) || $to->exists($path)) {
                    continue;
                }
                $to->put($path, $from->get($path));
                $from->delete($path);
            }
        });
    }
};
