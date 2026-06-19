<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // stripe_events: payment_intent_id queried on every webhook
        if (Schema::hasTable('stripe_events')) {
            Schema::table('stripe_events', function (Blueprint $table) {
                try { $table->index('payment_intent_id', 'stripe_events_pi_id_index'); } catch (\Exception $e) {}
            });
        }

        // mart_orders: promo_code lookup for per-user limit checks
        if (Schema::hasTable('mart_orders')) {
            Schema::table('mart_orders', function (Blueprint $table) {
                try { $table->index(['customer_id', 'promo_code', 'status'], 'mart_orders_promo_lookup_index'); } catch (\Exception $e) {}
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stripe_events')) {
            Schema::table('stripe_events', function (Blueprint $table) {
                try { $table->dropIndex('stripe_events_pi_id_index'); } catch (\Exception $e) {}
            });
        }
        if (Schema::hasTable('mart_orders')) {
            Schema::table('mart_orders', function (Blueprint $table) {
                try { $table->dropIndex('mart_orders_promo_lookup_index'); } catch (\Exception $e) {}
            });
        }
    }
};
