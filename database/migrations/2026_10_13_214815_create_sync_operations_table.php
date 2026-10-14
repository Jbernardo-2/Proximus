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
        Schema::create('sync_operations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('device_name', 120)->nullable();
            $table->string('client_reference', 100);
            $table->string('type', 40);
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status');
            $table->json('payload');
            $table->json('response')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(['user_id', 'client_reference'], 'sync_operations_user_reference_unique');
            $table->index(['status', 'processed_at'], 'sync_operations_status_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
