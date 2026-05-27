<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('trip_requests') && !Schema::hasColumn('trip_requests', 'delivery_notes')) {
            Schema::table('trip_requests', function (Blueprint $table) {
                $table->text('delivery_notes')->nullable()->after('pickup_note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trip_requests', 'delivery_notes')) {
            Schema::table('trip_requests', function (Blueprint $table) {
                $table->dropColumn('delivery_notes');
            });
        }
    }
};
