<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0003: subscription plans, each company's subscription, payments
 * declared by companies and confirmed by the platform, acceptance of the
 * terms of service and account closure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('monthly_price', 12, 2);
            $table->string('currency', 3)->default('USD');
            // Null: unlimited.
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_invoices_per_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Provisional prices and limits, editable from the platform area.
        $now = now();
        DB::table('plans')->insert([
            ['code' => 'essentiel', 'name' => 'Essentiel', 'description' => 'Pour démarrer : facturation et comptabilité de base.', 'monthly_price' => 15, 'currency' => 'USD', 'max_users' => 2, 'max_invoices_per_month' => 50, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'pro', 'name' => 'Pro', 'description' => 'Pour une équipe comptable et commerciale.', 'monthly_price' => 35, 'currency' => 'USD', 'max_users' => 5, 'max_invoices_per_month' => 300, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'entreprise', 'name' => 'Entreprise', 'description' => 'Sans limite d’utilisateurs ni de factures.', 'monthly_price' => 75, 'currency' => 'USD', 'max_users' => null, 'max_invoices_per_month' => null, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->date('trial_ends_at')->nullable()->after('suspended_at');
            $table->date('subscription_ends_at')->nullable()->after('trial_ends_at');
            // Complimentary access, granted by the platform.
            $table->boolean('billing_exempt')->default(false)->after('subscription_ends_at');
            $table->string('terms_version', 20)->nullable()->after('billing_exempt');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_version');
            $table->foreignId('terms_accepted_by')->nullable()->after('terms_accepted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('closure_requested_at')->nullable()->after('terms_accepted_by');
            $table->foreignId('closure_requested_by')->nullable()->after('closure_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('closure_requested_by');
        });

        // The companies that used Konta360 before subscriptions existed keep
        // their access until the platform decides otherwise.
        DB::table('companies')->update([
            'billing_exempt' => true,
            'plan_id' => DB::table('plans')->where('code', 'entreprise')->value('id'),
        ]);

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('months');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('method', 30);
            $table->string('reference');
            $table->string('status', 20)->default('pending');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('rejection_reason')->nullable();
            $table->date('period_starts_on')->nullable();
            $table->date('period_ends_on')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropConstrainedForeignId('terms_accepted_by');
            $table->dropConstrainedForeignId('closure_requested_by');
            $table->dropColumn(['trial_ends_at', 'subscription_ends_at', 'billing_exempt', 'terms_version', 'terms_accepted_at', 'closure_requested_at', 'closed_at']);
        });
        Schema::dropIfExists('plans');
    }
};
