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
        Schema::table('customer_receivables', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['delivery_run_order_id']);
            $table->dropUnique(['delivery_run_order_id']);
        });

        Schema::table('customer_receivables', function (Blueprint $table): void {
            $table->ulid('order_id')->nullable()->change();
            $table->ulid('delivery_run_order_id')->nullable()->change();
            $table->string('source', 30)->default('sale')->after('customer_id');
            $table->string('document_number', 80)->nullable()->after('delivery_run_order_id');
            $table->date('issued_at')->nullable()->after('opened_at');
            $table->date('due_date')->nullable()->after('issued_at');
            $table->unsignedSmallInteger('term_days')->nullable()->after('due_date');
            $table->text('notes')->nullable()->after('term_days');
            $table->timestamp('voided_at')->nullable()->after('paid_at');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');

            $table->index(['status', 'due_date', 'customer_id'], 'customer_receivables_due_index');
        });

        Schema::table('customer_receivables', function (Blueprint $table): void {
            $table->foreign('order_id', 'customer_receivables_order_fk')
                ->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('delivery_run_order_id', 'customer_receivables_run_order_fk')
                ->references('id')->on('delivery_run_orders')->restrictOnDelete();
            $table->unique('delivery_run_order_id', 'customer_receivables_run_order_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_receivables', function (Blueprint $table): void {
            $table->dropForeign('customer_receivables_order_fk');
            $table->dropForeign('customer_receivables_run_order_fk');
            $table->dropUnique('customer_receivables_run_order_unique');
            $table->dropIndex('customer_receivables_due_index');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn([
                'source',
                'document_number',
                'issued_at',
                'due_date',
                'term_days',
                'notes',
                'voided_at',
                'void_reason',
            ]);
            $table->ulid('order_id')->nullable(false)->change();
            $table->ulid('delivery_run_order_id')->nullable(false)->change();
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('delivery_run_order_id')->references('id')->on('delivery_run_orders')->restrictOnDelete();
            $table->unique('delivery_run_order_id');
        });
    }
};
