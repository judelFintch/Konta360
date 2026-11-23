<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Modules\Companies\Services\BrandImageProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Applies BrandImageProcessor to the logos, signatures and stamps uploaded
 * before it existed. Files it already produced are skipped, so the command
 * can be run again safely. A file that cannot be processed is kept as is.
 */
#[Signature('konta360:normalize-brand-images {--company= : Identifiant d’une seule société}')]
#[Description('Recadre et optimise les logos, signatures et cachets déjà envoyés')]
class NormalizeBrandImages extends Command
{
    /** Name given by CompanySettingController to processed files. */
    public const PROCESSED = '/(^|\/)(logo|signature|stamp)-[A-Za-z0-9]{32}\.png$/';

    public function handle(BrandImageProcessor $images): int
    {
        if (! extension_loaded('gd')) {
            $this->warn('Extension PHP GD absente : images laissées telles quelles.');

            return self::SUCCESS;
        }

        $disk = Company::brandDisk();
        $done = 0;
        $failed = 0;
        Company::query()
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->where(fn ($query) => $query->whereNotNull('logo_path')->orWhereNotNull('signature_path')->orWhereNotNull('stamp_path'))
            ->orderBy('id')
            ->each(function (Company $company) use ($images, $disk, &$done, &$failed) {
                foreach (Company::BRAND_ASSETS as $type) {
                    $column = $type.'_path';
                    $path = $company->{$column};
                    if (! $path || preg_match(self::PROCESSED, $path) || ! $disk->exists($path)) {
                        continue;
                    }

                    try {
                        $result = $images->process($disk->path($path), $type);
                        $newPath = $company->storageDirectory().'/'.$type.'-'.Str::random(32).'.png';
                        $disk->put($newPath, $result['png']);
                        $company->forceFill([$column => $newPath])->save();
                        $disk->delete($path);
                        $done++;
                        $this->line("Société {$company->id} — {$type} : {$result['width']} × {$result['height']} px");
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->warn("Société {$company->id} — {$type} : laissé tel quel ({$exception->getMessage()})");
                    }
                }
            });

        $this->info("{$done} image(s) optimisée(s), {$failed} laissée(s) telle(s) quelle(s).");

        return self::SUCCESS;
    }
}
