<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0002: the single company_settings row becomes company n°1 of the new
 * companies table, and every existing user joins it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_form')->nullable();
            $table->string('tax_identifier')->nullable();
            $table->string('national_identifier')->nullable();
            $table->string('cnss_number')->nullable();
            $table->string('trade_register')->nullable();
            $table->string('representative_name')->nullable();
            $table->string('representative_title')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_swift')->nullable();
            $table->string('mobile_money')->nullable();
            $table->string('default_currency', 3)->default('CDF');
            $table->decimal('default_tax_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('default_payment_days')->default(30);
            $table->unsignedSmallInteger('default_quote_validity_days')->default(30);
            $table->string('quote_prefix', 20)->default('DEV');
            $table->string('invoice_prefix', 20)->default('FAC');
            $table->string('credit_note_prefix', 20)->default('AVO');
            $table->unsignedTinyInteger('number_padding')->default(5);
            $table->string('logo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('stamp_path')->nullable();
            $table->text('invoice_footer')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        $settings = (array) (DB::table('company_settings')->orderBy('id')->first() ?? []);
        $columns = Schema::getColumnListing('companies');
        $company = array_intersect_key($settings, array_flip($columns));
        unset($company['id']);
        DB::table('companies')->insert($company + [
            'name' => config('app.name', 'Konta360'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $companyId = DB::table('companies')->min('id');

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->boolean('is_platform_admin')->default(false)->after('is_active');
        });
        DB::table('users')->update(['company_id' => $companyId]);

        Schema::drop('company_settings');
    }

    public function down(): void
    {
        throw new RuntimeException('Migration multi-sociétés irréversible : restaurer la sauvegarde de la base.');
    }
};
