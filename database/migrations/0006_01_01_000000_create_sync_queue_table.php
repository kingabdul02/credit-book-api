<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type'); // contact, debt, transaction, reminder
            $table->uuid('entity_id');
            $table->string('action'); // SyncAction enum
            $table->json('payload');
            $table->timestamp('client_timestamp');
            $table->timestamp('server_timestamp')->nullable();
            $table->string('status')->default('PENDING'); // SyncStatus enum
            $table->json('conflict_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'entity_type', 'entity_id']);
            $table->index(['user_id', 'server_timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
    }
};
