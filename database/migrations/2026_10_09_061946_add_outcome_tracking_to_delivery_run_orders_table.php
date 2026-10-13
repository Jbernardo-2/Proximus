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
        Schema::table('delivery_run_orders', function (Blueprint $table): void {
            $table->string('outcome_status', 30)->nullable()->after('status');
            $table->timestamp('outcome_recorded_at')->nullable()->after('outcome_notes');
            $table->foreignId('outcome_recorded_by')->nullable()->after('outcome_recorded_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_run_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('outcome_recorded_by');
            $table->dropColumn(['outcome_status', 'outcome_recorded_at']);
        });
    }
};
