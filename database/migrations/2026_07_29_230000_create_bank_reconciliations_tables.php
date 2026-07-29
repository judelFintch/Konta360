<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('treasury_account_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('statement_opening_balance', 18, 2);
            $table->decimal('statement_closing_balance', 18, 2);
            $table->decimal('calculated_closing_balance', 18, 2);
            $table->decimal('difference', 18, 2);
            $table->string('currency', 3);
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at');
            $table->foreignId('completed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['treasury_account_id', 'ends_on']);
        });

        Schema::create('bank_reconciliation_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treasury_transaction_id')->constrained()->restrictOnDelete();
            $table->unique(['bank_reconciliation_id', 'treasury_transaction_id'], 'bank_reconciliation_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_transactions');
        Schema::dropIfExists('bank_reconciliations');
    }
};
