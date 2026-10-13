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
        Schema::create('customer_credit_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('disabled');
            $table->decimal('credit_limit', 14, 4)->default(0);
            $table->unsignedSmallInteger('term_days')->default(0);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'credit_limit'], 'customer_credit_profiles_status_index');
        });

        Schema::create('customer_credit_profile_histories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->decimal('old_credit_limit', 14, 4)->nullable();
            $table->decimal('new_credit_limit', 14, 4);
            $table->unsignedSmallInteger('old_term_days')->nullable();
            $table->unsignedSmallInteger('new_term_days');
            $table->unsignedSmallInteger('old_grace_days')->nullable();
            $table->unsignedSmallInteger('new_grace_days');
            $table->text('reason');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at'], 'customer_credit_profile_history_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credit_profile_histories');
        Schema::dropIfExists('customer_credit_profiles');
    }
};
