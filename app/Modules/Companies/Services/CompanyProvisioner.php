<?php

namespace App\Modules\Companies\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role as RoleEnum;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Prepares a new company so it can work on its own: chart of accounts,
 * journals and its first administrator. Idempotent, so it can also bring an
 * existing company up to date.
 *
 * The chart below is the provisional one the application has used so far;
 * it is not an official SYSCOHADA chart and must be validated before
 * production use (see memory chart-of-accounts-status).
 */
class CompanyProvisioner
{
    public const ACCOUNTS = [
        ['code' => '28', 'name' => 'Amortissements cumulés', 'type' => 'contra_asset'],
        ['code' => '401', 'name' => 'Fournisseurs', 'type' => 'payable'],
        ['code' => '411', 'name' => 'Clients', 'type' => 'receivable'],
        ['code' => '4431', 'name' => 'Taxes sur ventes', 'type' => 'liability'],
        ['code' => '445', 'name' => 'Taxes déductibles', 'type' => 'asset'],
        ['code' => '512', 'name' => 'Banque', 'type' => 'asset'],
        ['code' => '571', 'name' => 'Caisse', 'type' => 'asset'],
        ['code' => '60', 'name' => 'Achats et charges', 'type' => 'expense'],
        ['code' => '65', 'name' => 'Autres charges de trésorerie', 'type' => 'expense'],
        ['code' => '68', 'name' => 'Dotations aux amortissements', 'type' => 'expense'],
        ['code' => '70', 'name' => 'Ventes', 'type' => 'revenue'],
        ['code' => '75', 'name' => 'Autres produits de trésorerie', 'type' => 'revenue'],
    ];

    public const JOURNALS = [
        ['code' => 'AC', 'name' => 'Journal des achats', 'type' => 'purchases'],
        ['code' => 'BQ', 'name' => 'Journal de banque', 'type' => 'bank'],
        ['code' => 'CA', 'name' => 'Journal de caisse', 'type' => 'cash'],
        ['code' => 'OD', 'name' => 'Opérations diverses', 'type' => 'general'],
        ['code' => 'VE', 'name' => 'Journal des ventes', 'type' => 'sales'],
    ];

    public function __construct(private readonly CurrentCompany $currentCompany) {}

    /**
     * Creates the company with its accounting set-up and its administrator,
     * all or nothing.
     *
     * @param  array<string, mixed>  $companyData
     * @param  array{name: string, email: string, password: string}  $adminData
     * @return array{0: Company, 1: User}
     */
    public function register(array $companyData, array $adminData): array
    {
        return DB::transaction(function () use ($companyData, $adminData) {
            $company = Company::create($companyData);
            $this->provision($company);

            $admin = new User($adminData);
            $admin->company()->associate($company);
            $admin->save();
            $admin->assignRole(RoleEnum::Administrateur->value);

            return [$company, $admin];
        });
    }

    public function provision(Company $company): void
    {
        $this->ensureRolesExist();

        $this->currentCompany->runAs($company, function (Company $company) {
            foreach ([Account::class => self::ACCOUNTS, Journal::class => self::JOURNALS] as $model => $rows) {
                foreach ($rows as $row) {
                    $record = $model::query()->firstOrNew(['code' => $row['code']], $row + ['is_active' => true]);
                    // Explicit: seeders run without model events, so the
                    // BelongsToCompany creating hook may not fire.
                    $record->company_id = $company->id;
                    $record->save();
                }
            }
        });
    }

    /**
     * Roles are shared by every company (ADR 0002 § 8); they only have to
     * exist once.
     */
    private function ensureRolesExist(): void
    {
        if (Role::query()->where('name', RoleEnum::Administrateur->value)->doesntExist()) {
            app(RolesAndPermissionsSeeder::class)->run();
        }
    }
}
