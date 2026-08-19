<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 20);
            $table->string('relationship', 100);
            $table->boolean('is_primary')->default(false);
            $table->string('verification_status', 20)->default('PENDING');
            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index(['user_id', 'verification_status']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_contacts');
    }
};
