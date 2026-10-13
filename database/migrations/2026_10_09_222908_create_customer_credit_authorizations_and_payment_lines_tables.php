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
        Schema::create('customer_credit_authorizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignUlid('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('delivery_run_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('requested_amount', 14, 4);
            $table->decimal('exposure_before', 14, 4);
            $table->decimal('credit_limit', 14, 4);
            $table->decimal('exceeded_by', 14, 4);
            $table->text('reason');
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('authorized_at');
            $table->timestamps();

            $table->index(['customer_id', 'authorized_at'], 'customer_credit_authorizations_timeline_index');
        });

        Schema::create('customer_payment_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_account_entry_id');
            $table->string('method', 30);
            $table->decimal('amount', 14, 4);
            $table->string('reference', 120)->nullable();
            $table->timestamps();

            $table->foreign('customer_account_entry_id', 'customer_payment_lines_entry_fk')
                ->references('id')
                ->on('customer_account_entries')
                ->restrictOnDelete();
            $table->index(['method', 'created_at'], 'customer_payment_lines_method_index');
        });

        Schema::table('customer_account_entries', function (Blueprint $table): void {
            $table->unique('reversal_of_id', 'customer_account_entries_reversal_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_account_entries', function (Blueprint $table): void {
            $table->dropUnique('customer_account_entries_reversal_unique');
        });
        Schema::dropIfExists('customer_payment_lines');
        Schema::dropIfExists('customer_credit_authorizations');
    }
};
