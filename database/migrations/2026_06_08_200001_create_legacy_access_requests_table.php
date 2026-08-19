<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_access_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('legacy_contact_id')->constrained('legacy_contacts')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->boolean('recovery_code_provided')->default(false);
            $table->string('status', 20)->default('PENDING');
            $table->unsignedInteger('waiting_period_days')->default(30);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('legacy_contact_id');
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_access_requests');
    }
};
