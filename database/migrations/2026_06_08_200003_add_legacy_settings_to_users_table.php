<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('legacy_access_enabled')->default(false)->after('settings');
            $table->string('legacy_recovery_code_hash')->nullable()->after('legacy_access_enabled');
            $table->unsignedInteger('legacy_waiting_period_days')->default(30)->after('legacy_recovery_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'legacy_access_enabled',
                'legacy_recovery_code_hash',
                'legacy_waiting_period_days',
            ]);
        });
    }
};
