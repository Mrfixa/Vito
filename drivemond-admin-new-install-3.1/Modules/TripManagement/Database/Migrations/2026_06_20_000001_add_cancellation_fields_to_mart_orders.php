<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mart_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('mart_orders', 'cancellation_reason')) {
                $table->string('cancellation_reason', 255)->nullable()->after('notes');
            }
            if (!Schema::hasColumn('mart_orders', 'cancelled_by')) {
                $table->string('cancelled_by', 20)->nullable()->after('cancellation_reason');
            }
            if (!Schema::hasColumn('mart_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mart_orders', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancelled_by', 'cancelled_at']);
        });
    }
};
