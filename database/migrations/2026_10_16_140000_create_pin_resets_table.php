<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pin_resets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone_number')->index();
            $table->string('email')->nullable();
            $table->string('code', 10);
            $table->string('reset_token', 80)->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['phone_number', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pin_resets');
    }
};
