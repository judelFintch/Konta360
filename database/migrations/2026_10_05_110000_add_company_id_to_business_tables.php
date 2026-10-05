<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0002 §§ 3 and 6: every business table gets a company_id, existing rows
 * go to company n°1, and uniqueness becomes per company.
 */
return new class extends Migration
{
    /**
     * Table => unique columns that become (company_id, ...columns).
     */
    private const TABLES = [
        'parties' => [['tax_identifier']],
        'catalog_items' => [['sku']],
        'quotes' => [['number']],
        'quote_lines' => [],
        'invoices' => [['number']],
        'invoice_lines' => [],
        'invoice_deductions' => [],
        'payments' => [['number']],
        'credit_notes' => [['number']],
        'credit_note_lines' => [],
        'accounts' => [['code']],
        'journals' => [['code']],
        'accounting_entries' => [['number']],
        'accounting_entry_lines' => [],
        'accounting_periods' => [['starts_on', 'ends_on']],
        'fixed_assets' => [['code']],
        'fixed_asset_depreciations' => [],
        'treasury_accounts' => [['name', 'currency']],
        'treasury_transactions' => [['number']],
        'bank_reconciliations' => [['number']],
        'expenses' => [['number']],
        'expense_payments' => [['number']],
        'audit_logs' => [],
    ];

    public function up(): void
    {
        $companyId = DB::table('companies')->min('id');

        foreach (self::TABLES as $tableName => $uniques) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
            });

            DB::table($tableName)->update(['company_id' => $companyId]);

            // NOT NULL first and the foreign key last: MySQL may refuse to
            // modify a column that already carries a foreign key. Indexes come
            // before the foreign key so that MySQL reuses them.
            Schema::table($tableName, function (Blueprint $table) use ($uniques) {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();

                foreach ($uniques as $columns) {
                    $table->dropUnique($columns);
                    $table->unique(['company_id', ...$columns]);
                }

                if ($uniques === []) {
                    $table->index('company_id');
                }

                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Migration multi-sociétés irréversible : restaurer la sauvegarde de la base.');
    }
};
