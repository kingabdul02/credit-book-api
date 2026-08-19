<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('nickname')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('profile_picture')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->unsignedInteger('usage_count')->default(0);
            $table->uuid('client_id')->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'full_name']);
            $table->index(['user_id', 'is_favorite']);
            $table->index(['user_id', 'usage_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
