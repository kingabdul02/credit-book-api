<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_access_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('legacy_contact_id')->nullable()->constrained('legacy_contacts')->nullOnDelete();
            $table->string('action', 50);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index('legacy_contact_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_access_audit_logs');
    }
};
