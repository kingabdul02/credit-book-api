<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type'); // TransactionType enum
            $table->decimal('original_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->text('note')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->default('UNPAID'); // DebtStatus enum
            $table->date('due_date')->nullable();
            $table->boolean('reminder_enabled')->default(false);
            $table->uuid('client_id')->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'transaction_type']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'contact_id']);
            $table->index(['user_id', 'due_date']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
