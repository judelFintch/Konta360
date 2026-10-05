<?php

namespace App\Modules\Companies\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Permanently deletes a closed company and everything it owns (ADR 0003 § 6).
 *
 * Accounting records must be kept for the legal retention period, so a
 * company can only be purged that long after its closure, unless the
 * operator explicitly overrides it (e.g. an empty test sign-up).
 */
class CompanyPurger
{
    /**
     * Children before parents, following the foreign keys.
     */
    private const TABLES = [
        'fixed_asset_depreciations', 'accounting_entry_lines', 'invoice_deductions', 'credit_note_lines',
        'credit_notes', 'payments', 'expense_payments', 'bank_reconciliations', 'treasury_transactions',
        'accounting_entries', 'invoice_lines', 'invoices', 'quote_lines', 'quotes', 'expenses',
        'fixed_assets', 'treasury_accounts', 'catalog_items', 'parties', 'accounts', 'journals',
        'accounting_periods', 'audit_logs', 'document_sequences', 'subscription_payments',
    ];

    public function purgeableFrom(Company $company): ?Carbon
    {
        return $company->closed_at?->copy()->addYears(config('konta360.retention_years'));
    }

    public function purge(Company $company, bool $ignoreRetention = false): void
    {
        if (! $company->isClosed()) {
            throw new LogicException('Seule une société clôturée peut être purgée.');
        }
        if (! $ignoreRetention && now()->lt($this->purgeableFrom($company))) {
            throw new LogicException('La durée légale de conservation court jusqu’au '.$this->purgeableFrom($company)->format('d/m/Y').'.');
        }

        DB::transaction(function () use ($company) {
            DB::table('bank_reconciliation_transactions')
                ->whereIn('bank_reconciliation_id', DB::table('bank_reconciliations')->where('company_id', $company->id)->select('id'))
                ->delete();

            foreach (self::TABLES as $table) {
                DB::table($table)->where('company_id', $company->id)->delete();
            }

            $users = DB::table('users')->where('company_id', $company->id);
            DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', (clone $users)->select('id'))->delete();
            DB::table('model_has_permissions')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', (clone $users)->select('id'))->delete();
            DB::table('password_reset_tokens')->whereIn('email', (clone $users)->select('email'))->delete();
            DB::table('sessions')->whereIn('user_id', (clone $users)->select('id'))->delete();
            DB::table('companies')->where('id', $company->id)->update(['terms_accepted_by' => null, 'closure_requested_by' => null]);
            $users->delete();

            DB::table('companies')->where('id', $company->id)->delete();
        });

        // Files stored before multi-company support live outside the
        // company's directory.
        Storage::disk('public')->delete(array_filter([$company->logo_path, $company->signature_path, $company->stamp_path]));
        Storage::disk('public')->deleteDirectory($company->storageDirectory());
    }
}
