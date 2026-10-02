<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('national_identifier')->nullable()->after('tax_identifier');
            $table->string('cnss_number')->nullable()->after('national_identifier');
            $table->string('representative_name')->nullable()->after('trade_register');
            $table->string('representative_title')->nullable()->after('representative_name');
            $table->string('bank_name')->nullable()->after('website');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_swift')->nullable()->after('bank_account_number');
            $table->string('mobile_money')->nullable()->after('bank_swift');
            $table->decimal('default_tax_rate', 5, 2)->default(0)->after('default_currency');
            $table->unsignedSmallInteger('default_payment_days')->default(30)->after('default_tax_rate');
            $table->unsignedSmallInteger('default_quote_validity_days')->default(30)->after('default_payment_days');
            $table->string('quote_prefix', 20)->default('DEV')->after('default_quote_validity_days');
            $table->string('invoice_prefix', 20)->default('FAC')->after('quote_prefix');
            $table->string('credit_note_prefix', 20)->default('AVO')->after('invoice_prefix');
            $table->unsignedTinyInteger('number_padding')->default(5)->after('credit_note_prefix');
            $table->string('logo_path')->nullable()->after('number_padding');
            $table->string('signature_path')->nullable()->after('logo_path');
            $table->string('stamp_path')->nullable()->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'national_identifier', 'cnss_number', 'representative_name', 'representative_title',
                'bank_name', 'bank_account_name', 'bank_account_number', 'bank_swift', 'mobile_money',
                'default_tax_rate', 'default_payment_days', 'default_quote_validity_days',
                'quote_prefix', 'invoice_prefix', 'credit_note_prefix', 'number_padding',
                'logo_path', 'signature_path', 'stamp_path',
            ]);
        });
    }
};
