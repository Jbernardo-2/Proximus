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
        Schema::create('price_tiers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_presentation_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_quantity', 18, 6);
            $table->decimal('max_quantity', 18, 6)->nullable();
            $table->decimal('unit_price', 14, 4);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_presentation_id', 'is_active', 'min_quantity'], 'price_tiers_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_tiers');
    }
};
