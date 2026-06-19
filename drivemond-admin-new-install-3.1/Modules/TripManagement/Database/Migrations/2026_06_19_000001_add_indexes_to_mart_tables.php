<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            ['mart_orders',      'customer_id', 'mart_orders_customer_id_index'],
            ['mart_orders',      'driver_id',   'mart_orders_driver_id_index'],
            ['mart_orders',      'status',      'mart_orders_status_index'],
            ['mart_order_items', 'order_id',    'mart_order_items_order_id_index'],
            ['mart_order_items', 'product_id',  'mart_order_items_product_id_index'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            try {
                Schema::table($table, function (Blueprint $t) use ($column, $name) {
                    $t->index($column, $name);
                });
            } catch (\Exception $e) {
                // Index already exists — safe to ignore
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            ['mart_orders',      'mart_orders_customer_id_index'],
            ['mart_orders',      'mart_orders_driver_id_index'],
            ['mart_orders',      'mart_orders_status_index'],
            ['mart_order_items', 'mart_order_items_order_id_index'],
            ['mart_order_items', 'mart_order_items_product_id_index'],
        ];

        foreach ($indexes as [$table, $name]) {
            try {
                Schema::table($table, function (Blueprint $t) use ($name) {
                    $t->dropIndex($name);
                });
            } catch (\Exception $e) {
                // Index did not exist — safe to ignore
            }
        }
    }
};
