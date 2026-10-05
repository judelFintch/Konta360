<?php

namespace App\Modules\Companies\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Builds a ZIP archive of everything a company owns: one CSV per table,
 * its settings and its branding files (ADR 0003 § 5). Lets a company take
 * its data elsewhere and keep its own copy before closing its account.
 *
 * Reads the tables directly, always filtered on the company explicitly.
 */
class CompanyDataExporter
{
    /**
     * Business tables, in an order a reader can follow.
     */
    public const TABLES = [
        'parties', 'catalog_items', 'quotes', 'quote_lines', 'invoices', 'invoice_lines',
        'invoice_deductions', 'credit_notes', 'credit_note_lines', 'payments', 'expenses',
        'expense_payments', 'treasury_accounts', 'treasury_transactions', 'bank_reconciliations',
        'accounts', 'journals', 'accounting_entries', 'accounting_entry_lines', 'accounting_periods',
        'fixed_assets', 'fixed_asset_depreciations', 'document_sequences', 'subscription_payments',
        'audit_logs',
    ];

    /**
     * Writes the archive to a temporary file and returns its path; the
     * caller deletes it once sent.
     */
    public function export(Company $company): string
    {
        $path = tempnam(sys_get_temp_dir(), 'konta360-export-');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer l’archive d’export.');
        }

        $zip->addFromString('LISEZMOI.txt', $this->readme($company));
        $zip->addFromString('societe.csv', $this->csv([collect($company->getAttributes())->except(['logo_path', 'signature_path', 'stamp_path'])->all()]));
        $zip->addFromString('utilisateurs.csv', $this->csv($this->users($company)));

        foreach (self::TABLES as $table) {
            $zip->addFromString("donnees/{$table}.csv", $this->csv(
                DB::table($table)->where('company_id', $company->id)->orderBy('id')->get()->map(fn ($row) => (array) $row),
                Schema::getColumnListing($table),
            ));
        }

        $zip->addFromString('donnees/bank_reconciliation_transactions.csv', $this->csv(
            DB::table('bank_reconciliation_transactions')
                ->whereIn('bank_reconciliation_id', DB::table('bank_reconciliations')->where('company_id', $company->id)->select('id'))
                ->orderBy('id')->get()->map(fn ($row) => (array) $row),
            Schema::getColumnListing('bank_reconciliation_transactions'),
        ));

        foreach (['logo', 'signature', 'stamp'] as $asset) {
            $file = $company->{$asset.'_path'};
            if ($file && Storage::disk('public')->exists($file)) {
                $zip->addFile(Storage::disk('public')->path($file), "fichiers/{$asset}.".pathinfo($file, PATHINFO_EXTENSION));
            }
        }

        $zip->close();

        return $path;
    }

    /**
     * Users without their password hash or tokens, with their role.
     */
    private function users(Company $company): array
    {
        return $company->users()->with('roles')->orderBy('id')->get()->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->pluck('name')->implode(', '),
            'is_active' => $user->is_active ? 1 : 0,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
        ])->all();
    }

    /**
     * Same format as the other exports: UTF-8 with BOM, « ; » separator.
     *
     * @param  iterable<array<string, mixed>>  $rows
     * @param  list<string>|null  $columns  Header of a table that may be empty.
     */
    private function csv(iterable $rows, ?array $columns = null): string
    {
        $output = fopen('php://temp', 'w+b');
        fwrite($output, "\xEF\xBB\xBF");
        if ($columns) {
            fputcsv($output, $columns, ';');
        }
        foreach ($rows as $row) {
            if (! $columns) {
                $columns = array_keys($row);
                fputcsv($output, $columns, ';');
            }
            fputcsv($output, array_map(fn ($column) => $this->sanitize($row[$column] ?? null), $columns), ';');
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Neutralises spreadsheet formulas without touching negative numbers.
     */
    private function sanitize(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^([=+@]|-(?!\d))/', $value) ? "'".$value : $value;
    }

    private function readme(Company $company): string
    {
        return implode("\n", [
            "Export des données de {$company->name}",
            'Généré le '.now()->format('d/m/Y à H:i').' par Konta360.',
            '',
            'societe.csv          Paramètres de la société.',
            'utilisateurs.csv     Utilisateurs et rôles (sans mots de passe).',
            'donnees/*.csv        Une table par fichier ; les colonnes *_id renvoient à la colonne id de la table liée.',
            'fichiers/            Logo, signature et cachet.',
            '',
            'Format : UTF-8, séparateur point-virgule. Les montants utilisent le point décimal.',
            'Conservez cette archive : les pièces comptables doivent être conservées dix ans.',
            '',
        ]);
    }
}
