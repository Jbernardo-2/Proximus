<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'is_default'], 'warehouses_active_default_index');
        });

        $defaultWarehouseId = (string) Str::ulid();
        $now = now();

        DB::table('warehouses')->insert([
            'id' => $defaultWarehouseId,
            'code' => 'BOD-001',
            'name' => 'Bodega principal',
            'address' => null,
            'is_default' => true,
            'is_active' => true,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('tracks_lots')->default(false)->after('allows_decimal');
            $table->boolean('tracks_expiration')->default(false)->after('tracks_lots');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUlid('warehouse_id')->nullable()->after('created_by')
                ->constrained('warehouses')->restrictOnDelete();
            $table->string('warehouse_code', 40)->nullable()->after('currency');
            $table->string('warehouse_name', 160)->nullable()->after('warehouse_code');
            $table->index(['warehouse_id', 'status'], 'orders_warehouse_status_index');
        });

        DB::table('orders')->update([
            'warehouse_id' => $defaultWarehouseId,
            'warehouse_code' => 'BOD-001',
            'warehouse_name' => 'Bodega principal',
        ]);

        Schema::create('inventory_counters', function (Blueprint $table) {
            $table->string('scope', 30);
            $table->char('period', 4);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->primary(['scope', 'period'], 'inventory_counters_primary');
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_on_hand', 18, 6)->default(0);
            $table->decimal('quantity_reserved', 18, 6)->default(0);
            $table->decimal('reorder_point', 18, 6)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id'], 'inventory_stock_warehouse_product_unique');
            $table->index(['warehouse_id', 'quantity_on_hand'], 'inventory_stock_on_hand_index');
        });

        foreach (DB::table('products')->whereNull('deleted_at')->pluck('id') as $productId) {
            DB::table('inventory_stocks')->insert([
                'id' => (string) Str::ulid(),
                'warehouse_id' => $defaultWarehouseId,
                'product_id' => $productId,
                'quantity_on_hand' => '0.000000',
                'quantity_reserved' => '0.000000',
                'reorder_point' => '0.000000',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('inventory_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('document_number', 40)->unique();
            $table->foreignUlid('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('status', 20)->default('draft');
            $table->date('occurred_on');
            $table->string('external_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status', 'occurred_on'], 'inventory_docs_warehouse_status_date_index');
            $table->index(['type', 'occurred_on'], 'inventory_docs_type_date_index');
        });

        Schema::create('inventory_document_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('inventory_document_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_presentation_id')->constrained()->restrictOnDelete();
            $table->string('product_sku', 80);
            $table->string('product_name', 180);
            $table->string('presentation_name', 120);
            $table->string('base_unit_symbol', 20);
            $table->decimal('conversion_factor', 18, 6);
            $table->decimal('quantity', 18, 6);
            $table->decimal('base_quantity', 18, 6);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->string('lot_number', 100)->nullable();
            $table->date('expiration_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['inventory_document_id', 'product_id'], 'inventory_doc_items_document_product_index');
            $table->index(['product_id', 'lot_number'], 'inventory_doc_items_product_lot_index');
        });

        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('count_number', 40)->unique();
            $table->foreignUlid('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->date('counted_on');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status', 'counted_on'], 'inventory_counts_warehouse_status_date_index');
        });

        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('inventory_count_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->restrictOnDelete();
            $table->string('product_sku', 80);
            $table->string('product_name', 180);
            $table->string('base_unit_symbol', 20);
            $table->decimal('expected_quantity', 18, 6);
            $table->decimal('counted_quantity', 18, 6)->nullable();
            $table->decimal('difference', 18, 6)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['inventory_count_id', 'product_id'], 'inventory_count_product_unique');
        });

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('order_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained()->restrictOnDelete();
            $table->decimal('base_quantity', 18, 6);
            $table->string('status', 20)->default('active');
            $table->timestamp('reserved_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'product_id', 'status'], 'inventory_reservations_stock_status_index');
            $table->index(['order_id', 'status'], 'inventory_reservations_order_status_index');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_presentation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('inventory_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('inventory_count_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->timestamp('occurred_at');
            $table->decimal('quantity_on_hand_delta', 18, 6)->default(0);
            $table->decimal('quantity_reserved_delta', 18, 6)->default(0);
            $table->decimal('quantity_on_hand_after', 18, 6);
            $table->decimal('quantity_reserved_after', 18, 6);
            $table->decimal('presentation_quantity', 18, 6)->nullable();
            $table->decimal('conversion_factor', 18, 6)->nullable();
            $table->string('product_sku', 80);
            $table->string('product_name', 180);
            $table->string('presentation_name', 120)->nullable();
            $table->string('base_unit_symbol', 20);
            $table->string('reference_number', 60)->nullable();
            $table->string('lot_number', 100)->nullable();
            $table->date('expiration_date')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'product_id', 'occurred_at'], 'inventory_movements_stock_timeline_index');
            $table->index(['type', 'occurred_at'], 'inventory_movements_type_date_index');
            $table->index(['lot_number', 'expiration_date'], 'inventory_movements_lot_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_counts');
        Schema::dropIfExists('inventory_document_items');
        Schema::dropIfExists('inventory_documents');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_counters');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex('orders_warehouse_status_index');
            $table->dropColumn(['warehouse_id', 'warehouse_code', 'warehouse_name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['tracks_lots', 'tracks_expiration']);
        });

        Schema::dropIfExists('warehouses');
    }
};
