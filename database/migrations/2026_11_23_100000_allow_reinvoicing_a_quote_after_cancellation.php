<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quote whose invoice was cancelled can be invoiced again, so several
 * invoices (one active at most) may point to the same quote. The rule is
 * enforced by InvoiceController::convert under a lock on the quote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // The foreign key needs an index once the unique one is gone (MySQL).
            $table->index('quote_id');
            $table->dropUnique(['quote_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('quote_id');
            $table->dropIndex(['quote_id']);
        });
    }
};
