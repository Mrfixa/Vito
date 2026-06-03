<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('token', 64)->unique();
            $table->string('role', 20);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('redeemed_by')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at');
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_tokens');
    }
};
