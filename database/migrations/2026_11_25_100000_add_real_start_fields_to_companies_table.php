<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('real_started_at')->nullable()->after('closed_at');
            $table->date('real_start_date')->nullable()->after('real_started_at');
            $table->string('real_start_archive_path')->nullable()->after('real_start_date');
            $table->foreignId('real_started_by')->nullable()->after('real_start_archive_path')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('real_started_by');
            $table->dropColumn(['real_started_at', 'real_start_date', 'real_start_archive_path']);
        });
    }
};
