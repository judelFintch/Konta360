<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_tax_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('old_tax_total', 18, 2);
            $table->decimal('new_tax_total', 18, 2);
            $table->decimal('old_total', 18, 2);
            $table->decimal('new_total', 18, 2);
            $table->json('line_changes');
            $table->text('reason');
            $table->timestamps();

            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_tax_corrections');
    }
};
