<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('document_language', 2)->default('fr')->after('address');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('language', 2)->default('fr')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('language');
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('document_language');
        });
    }
};
