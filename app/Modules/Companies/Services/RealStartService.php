<?php

namespace App\Modules\Companies\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

/**
 * Archives an evaluation workspace and clears its transactional records so
 * the company can begin real operations without mixing in test activity.
 */
class RealStartService
{
    /** Records that make up the trial ledger, in child-before-parent order. */
    private const TRANSACTION_TABLES = [
        'fixed_asset_depreciations',
        'invoice_deductions',
        'credit_note_lines',
        'invoice_tax_corrections',
        'invoice_label_corrections',
        'invoice_deduction_corrections',
        'payments',
        'expense_payments',
        'bank_reconciliations',
        'treasury_transactions',
        'accounting_entries',
        'invoice_lines',
        'credit_notes',
        'invoices',
        'quote_lines',
        'quotes',
        'expenses',
        'fixed_assets',
        'accounting_periods',
    ];

    public function __construct(private readonly CompanyDataExporter $exporter) {}

    /**
     * @return array<string, int> Counts displayed before a company confirms the transition.
     */
    public function preview(Company $company): array
    {
        return [
            'devis' => DB::table('quotes')->where('company_id', $company->id)->count(),
            'factures' => DB::table('invoices')->where('company_id', $company->id)->count(),
            'avoirs' => DB::table('credit_notes')->where('company_id', $company->id)->count(),
            'règlements' => DB::table('payments')->where('company_id', $company->id)->count(),
            'dépenses' => DB::table('expenses')->where('company_id', $company->id)->count(),
            'écritures' => DB::table('accounting_entries')->where('company_id', $company->id)->count(),
            'mouvements de trésorerie' => DB::table('treasury_transactions')->where('company_id', $company->id)->count(),
            'rapprochements' => DB::table('bank_reconciliations')->where('company_id', $company->id)->count(),
            'immobilisations' => DB::table('fixed_assets')->where('company_id', $company->id)->count(),
            'amortissements' => DB::table('fixed_asset_depreciations')->where('company_id', $company->id)->count(),
            'périodes comptables' => DB::table('accounting_periods')->where('company_id', $company->id)->count(),
        ];
    }

    /**
     * @param  array<int, array{keep?: bool|string, name?: string, type?: string, currency?: string, opening_balance?: numeric-string|int|float}>  $accounts
     */
    public function start(Company $company, string $startDate, array $accounts, int $userId): string
    {
        return DB::transaction(function () use ($company, $startDate, $accounts, $userId) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            if ($company->real_started_at) {
                throw new LogicException('Le démarrage en réel a déjà été effectué pour cette société.');
            }

            $existingIds = DB::table('treasury_accounts')->where('company_id', $company->id)
                ->lockForUpdate()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $submittedIds = array_map('intval', array_keys($accounts));
            sort($existingIds);
            sort($submittedIds);
            if ($existingIds !== $submittedIds) {
                throw new LogicException('La liste des comptes a changé. Rechargez la page puis recommencez.');
            }

            $temporaryArchive = $this->exporter->export($company);
            $archivePath = $company->storageDirectory().'/archives/pre-reel-'.now()->format('Ymd-His').'.zip';
            try {
                $contents = file_get_contents($temporaryArchive);
                if ($contents === false || ! Company::brandDisk()->put($archivePath, $contents)) {
                    throw new RuntimeException('Impossible d’enregistrer l’archive de sauvegarde. Aucune donnée n’a été supprimée.');
                }
            } finally {
                if (is_file($temporaryArchive)) {
                    unlink($temporaryArchive);
                }
            }

            DB::table('bank_reconciliation_transactions')
                ->whereIn('bank_reconciliation_id', DB::table('bank_reconciliations')->where('company_id', $company->id)->select('id'))
                ->delete();

            DB::table('accounting_entry_lines')
                ->whereIn('accounting_entry_id', DB::table('accounting_entries')->where('company_id', $company->id)->select('id'))
                ->delete();

            foreach (self::TRANSACTION_TABLES as $table) {
                DB::table($table)->where('company_id', $company->id)->delete();
            }

            $retainedAccounts = array_filter($accounts, fn (array $data) => filter_var($data['keep'] ?? false, FILTER_VALIDATE_BOOLEAN));
            $retainedAccountIds = array_map('intval', array_keys($retainedAccounts));
            DB::table('treasury_accounts')->where('company_id', $company->id)
                ->when($retainedAccountIds, fn ($query) => $query->whereNotIn('id', $retainedAccountIds))
                ->delete();

            foreach ($retainedAccountIds as $id) {
                DB::table('treasury_accounts')->where('company_id', $company->id)->where('id', $id)
                    ->update(['name' => '__reel_'.Str::uuid(), 'updated_at' => now()]);
            }

            foreach ($retainedAccounts as $id => $data) {
                $id = (int) $id;
                DB::table('treasury_accounts')->where('company_id', $company->id)->where('id', $id)->update([
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'currency' => $data['currency'],
                    'opening_balance' => round((float) $data['opening_balance'], 2),
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            }

            $company->forceFill([
                'real_started_at' => now(),
                'real_start_date' => $startDate,
                'real_start_archive_path' => $archivePath,
                'real_started_by' => $userId,
            ])->save();

            return $archivePath;
        });
    }

    public function archivePath(Company $company): ?string
    {
        $path = $company->real_start_archive_path;

        return $path && Company::brandDisk()->exists($path) ? $path : null;
    }
}
