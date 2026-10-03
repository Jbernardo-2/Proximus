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
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('business_name');
            $table->string('business_type', 80)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address');
            $table->text('reference')->nullable();
            $table->decimal('latitude', 9, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'business_name'], 'customers_active_name_index');
            $table->index('business_type');
        });

        Schema::create('sales_routes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name'], 'sales_routes_active_name_index');
            $table->index(['salesperson_id', 'is_active'], 'sales_routes_salesperson_index');
            $table->index(['driver_id', 'is_active'], 'sales_routes_driver_index');
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sales_route_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('visit_day');
            $table->unsignedSmallInteger('visit_order');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['sales_route_id', 'customer_id', 'visit_day'],
                'route_stops_route_customer_day_unique',
            );
            $table->index(
                ['sales_route_id', 'visit_day', 'is_active', 'visit_order'],
                'route_stops_schedule_index',
            );
            $table->index(['customer_id', 'is_active'], 'route_stops_customer_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('sales_routes');
        Schema::dropIfExists('customers');
    }
};
