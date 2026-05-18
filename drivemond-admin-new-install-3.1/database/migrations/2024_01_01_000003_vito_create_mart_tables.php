<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mart_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('image')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('stock')->default(0);
            $table->foreignUuid('zone_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'category']);
        });

        Schema::create('mart_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ref_id', 20)->unique();
            $table->foreignUuid('customer_id')->constrained('users');
            $table->foreignUuid('driver_id')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'accepted', 'picked_up', 'delivered', 'cancelled'])->default('pending');
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();
            $table->string('signature_image')->nullable();
            $table->string('delivery_photo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index(['driver_id', 'status']);
        });

        Schema::create('mart_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('mart_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('mart_products');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mart_order_items');
        Schema::dropIfExists('mart_orders');
        Schema::dropIfExists('mart_products');
    }
};
