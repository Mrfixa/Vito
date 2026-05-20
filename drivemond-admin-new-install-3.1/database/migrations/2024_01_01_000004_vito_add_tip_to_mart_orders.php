<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mart_orders', function (Blueprint $table) {
            $table->decimal('tip_amount', 10, 2)->default(0)->after('total_amount');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('tip_amount');
            $table->string('promo_code')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('mart_orders', function (Blueprint $table) {
            $table->dropColumn(['tip_amount', 'discount_amount', 'promo_code']);
        });
    }
};
