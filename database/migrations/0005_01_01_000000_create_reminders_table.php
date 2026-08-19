<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('remind_at');
            $table->string('type'); // ReminderType enum
            $table->string('schedule'); // ReminderSchedule enum
            $table->boolean('is_sent')->default(false);
            $table->uuid('client_id')->unique();
            $table->timestamps();

            $table->index(['user_id', 'is_sent', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
