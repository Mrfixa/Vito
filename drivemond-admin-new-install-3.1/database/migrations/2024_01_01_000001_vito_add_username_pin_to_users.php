<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('email');
            $table->string('pin_hash')->nullable()->after('password');
            $table->unsignedSmallInteger('pin_attempts')->default(0)->after('failed_attempt');
            $table->timestamp('pin_blocked_at')->nullable()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'pin_hash', 'pin_attempts', 'pin_blocked_at']);
        });
    }
};
