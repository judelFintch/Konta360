<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Modules\Companies\Services\CompanyPurger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use LogicException;

use function Laravel\Prompts\confirm;

/**
 * Permanently deletes a closed company once the legal retention period is
 * over (ADR 0003 § 6).
 */
#[Signature('konta360:purge-company {company : Identifiant de la société} {--ignore-retention : Purger avant la fin de la durée légale de conservation (ex. inscription de test vide)}')]
#[Description('Supprime définitivement une société clôturée et toutes ses données')]
class PurgeCompany extends Command
{
    public function handle(CompanyPurger $purger): int
    {
        $company = Company::find($this->argument('company'));
        if (! $company) {
            $this->error('Société introuvable.');

            return self::FAILURE;
        }

        $this->warn("Suppression définitive de « {$company->name} » (n° {$company->id}), de ses utilisateurs et de toutes ses données.");
        if ($this->option('ignore-retention')) {
            $this->warn('La durée légale de conservation des pièces comptables sera ignorée.');
        }
        if (! confirm('Confirmer la suppression définitive ?', default: false)) {
            return self::FAILURE;
        }

        try {
            $purger->purge($company, $this->option('ignore-retention'));
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Société supprimée.');

        return self::SUCCESS;
    }
}
