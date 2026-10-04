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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('license_plate', 30)->nullable()->unique();
            $table->string('description', 180);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'description'], 'vehicles_active_description_index');
        });

        Schema::create('delivery_counters', function (Blueprint $table) {
            $table->string('scope', 30);
            $table->char('period', 4);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->primary(['scope', 'period'], 'delivery_counters_primary');
        });

        Schema::create('delivery_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('run_number', 40)->unique();
            $table->string('client_reference', 100)->nullable()->unique();
            $table->foreignUlid('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('scheduled_date');
            $table->string('status', 30)->default('draft');
            $table->string('warehouse_code', 40);
            $table->string('warehouse_name', 160);
            $table->string('driver_name', 160);
            $table->string('vehicle_code', 40)->nullable();
            $table->string('vehicle_license_plate', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('preparation_started_at')->nullable();
            $table->foreignId('preparation_started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('loaded_at')->nullable();
            $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('departed_at')->nullable();
            $table->foreignId('departed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('loaded_total', 14, 4)->default(0);
            $table->decimal('delivered_total', 14, 4)->default(0);
            $table->decimal('collected_total', 14, 4)->default(0);
            $table->decimal('cash_expected', 14, 4)->default(0);
            $table->decimal('cash_declared', 14, 4)->nullable();
            $table->decimal('cash_difference', 14, 4)->default(0);
            $table->decimal('transfer_total', 14, 4)->default(0);
            $table->decimal('card_total', 14, 4)->default(0);
            $table->decimal('check_total', 14, 4)->default(0);
            $table->decimal('other_payment_total', 14, 4)->default(0);
            $table->decimal('credit_total', 14, 4)->default(0);
            $table->text('settlement_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_date'], 'delivery_runs_status_date_index');
            $table->index(['driver_id', 'status', 'scheduled_date'], 'delivery_runs_driver_status_date_index');
            $table->index(['warehouse_id', 'status'], 'delivery_runs_warehouse_status_index');
        });

        Schema::create('delivery_run_status_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('delivery_run_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['delivery_run_id', 'created_at'], 'delivery_run_history_timeline_index');
        });

        Schema::create('delivery_run_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('delivery_run_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('visit_order');
            $table->string('status', 30)->default('pending');
            $table->decimal('requested_total', 14, 4);
            $table->decimal('delivered_total', 14, 4)->default(0);
            $table->decimal('collected_total', 14, 4)->default(0);
            $table->decimal('balance_due', 14, 4)->default(0);
            $table->string('outcome_reason', 40)->nullable();
            $table->text('outcome_notes')->nullable();
            $table->text('credit_reason')->nullable();
            $table->string('receiver_name', 160)->nullable();
            $table->decimal('latitude', 9, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['delivery_run_id', 'order_id'], 'delivery_run_order_unique');
            $table->index(['order_id', 'status'], 'delivery_run_orders_order_status_index');
            $table->unique(['delivery_run_id', 'visit_order'], 'delivery_run_orders_visit_unique');
        });

        Schema::create('delivery_run_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('delivery_run_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_presentation_id')->constrained()->restrictOnDelete();
            $table->string('product_sku', 80);
            $table->string('product_name', 180);
            $table->string('presentation_name', 120);
            $table->string('base_unit_symbol', 30);
            $table->decimal('conversion_factor', 18, 6);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('requested_quantity', 18, 6);
            $table->decimal('requested_base_quantity', 20, 6);
            $table->decimal('prepared_quantity', 18, 6)->default(0);
            $table->decimal('prepared_base_quantity', 20, 6)->default(0);
            $table->decimal('loaded_quantity', 18, 6)->default(0);
            $table->decimal('loaded_base_quantity', 20, 6)->default(0);
            $table->decimal('delivered_quantity', 18, 6)->default(0);
            $table->decimal('delivered_base_quantity', 20, 6)->default(0);
            $table->decimal('returned_quantity', 18, 6)->default(0);
            $table->decimal('returned_base_quantity', 20, 6)->default(0);
            $table->decimal('damaged_quantity', 18, 6)->default(0);
            $table->decimal('damaged_base_quantity', 20, 6)->default(0);
            $table->decimal('missing_quantity', 18, 6)->default(0);
            $table->decimal('missing_base_quantity', 20, 6)->default(0);
            $table->decimal('delivered_line_total', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['delivery_run_order_id', 'order_item_id'], 'delivery_run_item_order_item_unique');
            $table->index(['product_id', 'product_presentation_id'], 'delivery_run_items_product_index');
        });

        Schema::create('delivery_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('receipt_number', 40)->unique();
            $table->string('client_reference', 100)->nullable()->unique();
            $table->foreignUlid('delivery_run_order_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('method', 30);
            $table->decimal('amount', 14, 4);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['delivery_run_order_id', 'status'], 'delivery_payments_order_status_index');
            $table->index(['method', 'received_at'], 'delivery_payments_method_date_index');
        });

        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->timestamp('fulfilled_at')->nullable()->after('released_at');
            $table->foreignId('fulfilled_by')->nullable()->after('released_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignUlid('delivery_run_id')->nullable()->after('order_item_id')
                ->constrained()->restrictOnDelete();
            $table->foreignUlid('delivery_run_order_id')->nullable()->after('delivery_run_id')
                ->constrained()->restrictOnDelete();
            $table->foreignUlid('delivery_run_item_id')->nullable()->after('delivery_run_order_id')
                ->constrained()->restrictOnDelete();

            $table->index(['delivery_run_id', 'occurred_at'], 'inventory_movements_delivery_run_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('inventory_movements_delivery_run_index');
            $table->dropConstrainedForeignId('delivery_run_item_id');
            $table->dropConstrainedForeignId('delivery_run_order_id');
            $table->dropConstrainedForeignId('delivery_run_id');
        });

        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fulfilled_by');
            $table->dropColumn('fulfilled_at');
        });

        Schema::dropIfExists('delivery_payments');
        Schema::dropIfExists('delivery_run_items');
        Schema::dropIfExists('delivery_run_orders');
        Schema::dropIfExists('delivery_run_status_histories');
        Schema::dropIfExists('delivery_runs');
        Schema::dropIfExists('delivery_counters');
        Schema::dropIfExists('vehicles');
    }
};
