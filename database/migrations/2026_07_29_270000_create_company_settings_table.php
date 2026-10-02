<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_form')->nullable();
            $table->string('tax_identifier')->nullable();
            $table->string('trade_register')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('default_currency', 3)->default('CDF');
            $table->text('invoice_footer')->nullable();
            $table->timestamps();
        });

        DB::table('company_settings')->insert([
            'name' => config('app.name', 'Konta360'),
            'default_currency' => 'CDF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
