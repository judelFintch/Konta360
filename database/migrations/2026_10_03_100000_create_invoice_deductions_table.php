<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 30);
            $table->string('description');
            $table->decimal('quantity', 15, 3);
            // Four decimals: amounts agreed with the customer are often the
            // product of a rounded total divided by a quantity.
            $table->decimal('unit_price', 18, 4);
            $table->decimal('amount', 18, 2);
            // Advance only: how and when the money was received.
            $table->date('received_on')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->foreignId('treasury_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->restrictOnDelete();

            $table->unique(['invoice_id', 'position']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('deductions_total', 18, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('deductions_total');
        });
        Schema::dropIfExists('invoice_deductions');
    }
};
