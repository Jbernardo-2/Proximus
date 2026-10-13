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
        Schema::create('delivery_settlements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('delivery_run_order_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('total_amount', 14, 4);
            $table->char('currency', 3)->default('HNL');
            $table->timestamp('settled_at');
            $table->foreignId('settled_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('delivery_settlement_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('delivery_settlement_id')->constrained()->cascadeOnDelete();
            $table->string('method', 30);
            $table->decimal('amount', 14, 4);
            $table->string('reference', 120)->nullable();
            $table->foreignUlid('delivery_payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('customer_receivable_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['method', 'created_at'], 'delivery_settlement_lines_method_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_settlement_lines');
        Schema::dropIfExists('delivery_settlements');
    }
};
