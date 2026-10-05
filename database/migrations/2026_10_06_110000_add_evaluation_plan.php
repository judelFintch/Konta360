<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0003 § 1: every new company starts on a free, one-month evaluation
 * plan, which cannot be bought, then chooses a paid plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_evaluation')->default(false)->after('max_invoices_per_month');
        });

        // Provisional limits, editable from the platform area.
        DB::table('plans')->insert([
            'code' => 'evaluation',
            'name' => 'Évaluation',
            'description' => 'Un mois gratuit pour découvrir Konta360 avec tous ses modules.',
            'monthly_price' => 0,
            'currency' => 'USD',
            'max_users' => 3,
            'max_invoices_per_month' => 30,
            'is_evaluation' => true,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $evaluation = DB::table('plans')->where('code', 'evaluation')->value('id');
        $fallback = DB::table('plans')->where('code', 'pro')->value('id');
        DB::table('companies')->where('plan_id', $evaluation)->update(['plan_id' => $fallback]);
        DB::table('plans')->where('id', $evaluation)->delete();

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_evaluation');
        });
    }
};
