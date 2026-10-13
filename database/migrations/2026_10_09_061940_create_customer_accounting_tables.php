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
        Schema::create('customer_receivables', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('order_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('delivery_run_order_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('original_amount', 14, 4);
            $table->decimal('outstanding_amount', 14, 4);
            $table->char('currency', 3)->default('HNL');
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'opened_at'], 'customer_receivables_open_index');
            $table->index(['order_id', 'status'], 'customer_receivables_order_index');
        });

        Schema::create('customer_account_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('status', 20)->default('active');
            $table->string('receipt_number', 40)->nullable()->unique();
            $table->foreignUlid('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('delivery_run_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('delivery_payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('method', 30)->nullable();
            $table->decimal('debit_amount', 14, 4)->default(0);
            $table->decimal('credit_amount', 14, 4)->default(0);
            $table->char('currency', 3)->default('HNL');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('reversal_of_id')->nullable()->constrained('customer_account_entries')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'occurred_at'], 'customer_account_entries_timeline_index');
            $table->index(['customer_id', 'type', 'occurred_at'], 'customer_account_entries_type_index');
        });

        Schema::create('customer_receivable_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_receivable_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('customer_account_entry_id');
            $table->decimal('amount', 14, 4);
            $table->timestamps();

            $table->foreign('customer_account_entry_id', 'cra_account_entry_fk')
                ->references('id')
                ->on('customer_account_entries')
                ->restrictOnDelete();
            $table->unique(
                ['customer_receivable_id', 'customer_account_entry_id'],
                'customer_receivable_entry_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_receivable_allocations');
        Schema::dropIfExists('customer_account_entries');
        Schema::dropIfExists('customer_receivables');
    }
};
