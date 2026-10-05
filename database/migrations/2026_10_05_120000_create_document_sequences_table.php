<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0002 § 7: document numbers come from a per company, per type and per
 * year sequence. Existing numbers are kept; each sequence resumes after the
 * highest number already issued that year, so no number can be reused.
 */
return new class extends Migration
{
    /**
     * Sequence type => table holding the numbered documents (frozen copy of
     * App\Modules\Companies\Enums\SequenceType at the time of writing).
     */
    private const SEQUENCES = [
        'quote' => ['quotes', 'number'],
        'invoice' => ['invoices', 'number'],
        'credit_note' => ['credit_notes', 'number'],
        'payment' => ['payments', 'number'],
        'accounting_entry' => ['accounting_entries', 'number'],
        'treasury_transaction' => ['treasury_transactions', 'number'],
        'bank_reconciliation' => ['bank_reconciliations', 'number'],
        'expense' => ['expenses', 'number'],
        'expense_payment' => ['expense_payments', 'number'],
        'fixed_asset' => ['fixed_assets', 'code'],
    ];

    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number');

            $table->unique(['company_id', 'type', 'year']);
        });

        foreach (self::SEQUENCES as $type => [$tableName, $column]) {
            $last = [];
            DB::table($tableName)
                ->whereNotNull($column)
                ->select(['id', 'company_id', "{$column} as number"])
                ->orderBy('id')
                ->each(function (object $row) use (&$last) {
                    if (! preg_match('/-(\d{4})-(\d+)$/', $row->number, $matches)) {
                        return;
                    }
                    $key = $row->company_id.'|'.$matches[1];
                    $last[$key] = max($last[$key] ?? 0, (int) $matches[2]);
                });

            foreach ($last as $key => $number) {
                [$companyId, $year] = explode('|', $key);
                DB::table('document_sequences')->insert([
                    'company_id' => (int) $companyId,
                    'type' => $type,
                    'year' => (int) $year,
                    'last_number' => $number,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
