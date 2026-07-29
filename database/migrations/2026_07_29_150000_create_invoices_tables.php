<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('quote_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 18, 2);
            $table->decimal('discount_total', 18, 2);
            $table->decimal('tax_total', 18, 2);
            $table->decimal('total', 18, 2);
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'issue_date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('sku', 80);
            $table->string('description');
            $table->string('unit', 30);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_rate', 5, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('subtotal', 18, 2);
            $table->decimal('discount_amount', 18, 2);
            $table->decimal('tax_amount', 18, 2);
            $table->decimal('total', 18, 2);

            $table->unique(['invoice_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
