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
        Schema::create('customer_visits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('client_reference', 100)->nullable()->unique();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->nullableUlidMorphs('subject');
            $table->string('reason', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('location_status', 20);
            $table->decimal('latitude', 9, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_meters', 8, 1)->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['occurred_at', 'user_id'], 'customer_visits_date_user_index');
            $table->index(['customer_id', 'occurred_at'], 'customer_visits_customer_date_index');
            $table->index(['type', 'occurred_at'], 'customer_visits_type_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_visits');
    }
};
