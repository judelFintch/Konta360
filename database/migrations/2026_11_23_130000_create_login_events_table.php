<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-in history: successful sign-ins (with the code or on a trusted
 * device), failed attempts on an existing account, and sign-outs. Shown to
 * the user on their profile and to the company administrators.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Copied from the user at the time: the history stays with the company.
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event', 30);
            $table->string('method', 30)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
