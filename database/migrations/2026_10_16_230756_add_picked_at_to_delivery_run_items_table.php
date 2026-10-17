<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_run_items', function (Blueprint $table) {
            // When the keeper reviewed the line: a prepared zero is a decision, an unreviewed line is not.
            $table->timestamp('picked_at')->nullable()->after('prepared_base_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_run_items', function (Blueprint $table) {
            $table->dropColumn('picked_at');
        });
    }
};
