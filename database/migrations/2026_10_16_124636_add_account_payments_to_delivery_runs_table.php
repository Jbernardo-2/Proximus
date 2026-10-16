<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account payments the driver collected during the run: the cash part is part of
     * the cash they hand over (cash_expected), the total is shown apart.
     */
    public function up(): void
    {
        Schema::table('delivery_runs', function (Blueprint $table) {
            $table->decimal('account_payment_cash', 14, 4)->default(0)->after('cash_expected');
            $table->decimal('account_payment_total', 14, 4)->default(0)->after('account_payment_cash');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_runs', function (Blueprint $table) {
            $table->dropColumn(['account_payment_cash', 'account_payment_total']);
        });
    }
};
