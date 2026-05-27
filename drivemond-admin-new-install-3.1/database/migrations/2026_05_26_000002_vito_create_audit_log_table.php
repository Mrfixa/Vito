<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('vito_audit_log')) {
            Schema::create('vito_audit_log', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable()->index();
                $table->string('action', 50);
                $table->string('model_type', 200);
                $table->string('model_id', 100);
                $table->json('changes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vito_audit_log');
    }
};
