<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->date('acquisition_date');
            $table->date('in_service_date');
            $table->decimal('acquisition_cost', 18, 2);
            $table->decimal('residual_value', 18, 2)->default(0);
            $table->string('currency', 3);
            $table->unsignedInteger('useful_life_months');
            $table->string('status', 20)->default('active');
            $table->foreignId('supplier_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index('in_service_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
