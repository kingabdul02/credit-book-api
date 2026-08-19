<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('pin_hash', 'login_pin_hash');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('transaction_pin_hash')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('transaction_pin_hash');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('login_pin_hash', 'pin_hash');
        });
    }
};
