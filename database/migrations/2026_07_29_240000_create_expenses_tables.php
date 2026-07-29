<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->string('supplier_reference')->nullable();
            $table->date('expense_date');
            $table->date('due_date');
            $table->string('description');
            $table->string('currency', 3);
            $table->decimal('subtotal', 18, 2);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->string('status', 20);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });

        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->restrictOnDelete();
            $table->foreignId('treasury_account_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable()->unique();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('accounts')->insert([
            ['code' => '401', 'name' => 'Fournisseurs', 'type' => 'payable', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '445', 'name' => 'Taxes déductibles', 'type' => 'asset', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '60', 'name' => 'Achats et charges', 'type' => 'expense', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('journals')->insert([
            ['code' => 'AC', 'name' => 'Journal des achats', 'type' => 'purchases', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
        Schema::dropIfExists('expenses');
        DB::table('journals')->where('code', 'AC')->delete();
        DB::table('accounts')->whereIn('code', ['401', '445', '60'])->delete();
    }
};
