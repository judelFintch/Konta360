<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained()->restrictOnDelete();
            $table->date('period_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('accounting_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_date']);
        });

        $now = now();
        DB::table('accounts')->insertOrIgnore([
            ['code' => '28', 'name' => 'Amortissements cumulés', 'type' => 'contra_asset', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '68', 'name' => 'Dotations aux amortissements', 'type' => 'expense', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('journals')->insertOrIgnore([
            ['code' => 'OD', 'name' => 'Opérations diverses', 'type' => 'general', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciations');
        DB::table('accounts')->whereIn('code', ['28', '68'])->delete();
        DB::table('journals')->where('code', 'OD')->delete();
    }
};
