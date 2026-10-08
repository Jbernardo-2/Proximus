<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_run_orders', function (Blueprint $table): void {
            $table->timestamp('prepared_at')->nullable()->after('status');
            $table->foreignId('prepared_by')->nullable()->after('prepared_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_run_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropColumn('prepared_at');
        });
    }
};
