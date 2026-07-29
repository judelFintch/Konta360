<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('type', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('type', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->date('entry_date');
            $table->string('label');
            $table->string('currency', 3);
            $table->string('status', 20)->default('draft');
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index(['entry_date', 'status']);
        });

        Schema::create('accounting_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);

            $table->unique(['accounting_entry_id', 'position']);
        });

        $now = now();
        DB::table('accounts')->insert([
            ['code' => '411', 'name' => 'Clients', 'type' => 'receivable', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '4431', 'name' => 'Taxes sur ventes', 'type' => 'liability', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '70', 'name' => 'Ventes', 'type' => 'revenue', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '512', 'name' => 'Banque', 'type' => 'asset', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '571', 'name' => 'Caisse', 'type' => 'asset', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('journals')->insert([
            ['code' => 'VE', 'name' => 'Journal des ventes', 'type' => 'sales', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'BQ', 'name' => 'Journal de banque', 'type' => 'bank', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CA', 'name' => 'Journal de caisse', 'type' => 'cash', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entry_lines');
        Schema::dropIfExists('accounting_entries');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('accounts');
    }
};
