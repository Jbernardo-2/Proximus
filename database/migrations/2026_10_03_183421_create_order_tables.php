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
        Schema::create('order_counters', function (Blueprint $table) {
            $table->char('period', 4)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('order_number', 30)->unique();
            $table->string('client_reference', 100)->nullable()->unique();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('sales_route_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('route_stop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('salesperson_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('order_date');
            $table->date('requested_delivery_date')->nullable();
            $table->string('payment_term', 20);
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3)->default('HNL');
            $table->string('customer_code', 40);
            $table->string('customer_name');
            $table->text('customer_address');
            $table->string('route_code', 40)->nullable();
            $table->string('route_name')->nullable();
            $table->unsignedTinyInteger('route_visit_day')->nullable();
            $table->unsignedSmallInteger('route_visit_order')->nullable();
            $table->string('salesperson_name');
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'order_date'], 'orders_status_date_index');
            $table->index(['salesperson_id', 'status', 'order_date'], 'orders_salesperson_status_index');
            $table->index(['sales_route_id', 'order_date'], 'orders_route_date_index');
            $table->index(['customer_id', 'order_date'], 'orders_customer_date_index');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_presentation_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('price_tier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_sku', 80);
            $table->string('product_name');
            $table->string('presentation_name');
            $table->string('base_unit_symbol', 30);
            $table->decimal('conversion_factor', 18, 6);
            $table->decimal('quantity', 18, 6);
            $table->decimal('base_quantity', 20, 6);
            $table->decimal('standard_unit_price', 14, 4);
            $table->decimal('unit_price', 14, 4);
            $table->string('price_source', 20);
            $table->foreignId('price_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();
            $table->decimal('line_total', 14, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['order_id', 'product_presentation_id'],
                'order_items_order_presentation_unique',
            );
            $table->index(['product_id', 'product_presentation_id'], 'order_items_product_index');
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at'], 'order_history_timeline_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_counters');
    }
};
