<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('sku', 80)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('unité');
            $table->decimal('unit_price', 18, 2);
            $table->string('currency', 3)->default('CDF');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
