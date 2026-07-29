<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20);
            $table->string('currency', 3);
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['name', 'currency']);
        });

        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('treasury_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_account_id')->nullable()->constrained('treasury_accounts')->restrictOnDelete();
            $table->string('type', 20);
            $table->date('transaction_date');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('description');
            $table->string('reference')->nullable();
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index(['treasury_account_id', 'transaction_date']);
            $table->index(['destination_account_id', 'transaction_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('treasury_account_id')->nullable()->after('invoice_id')->constrained()->restrictOnDelete();
        });

        $now = now();
        DB::table('accounts')->insert([
            ['code' => '65', 'name' => 'Autres charges de trésorerie', 'type' => 'expense', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '75', 'name' => 'Autres produits de trésorerie', 'type' => 'revenue', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treasury_account_id');
        });
        Schema::dropIfExists('treasury_transactions');
        Schema::dropIfExists('treasury_accounts');
        DB::table('accounts')->whereIn('code', ['65', '75'])->delete();
    }
};
