<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // LedgerType enum
            $table->decimal('amount', 15, 2);
            $table->text('note')->nullable();
            $table->uuid('client_id')->unique();
            $table->timestamp('created_at')->useCurrent();

            // No updated_at — transactions are immutable
            $table->index(['debt_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
