<?php

namespace App\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateDeliveryNumberAction
{
    public function handle(string $scope, string $prefix, CarbonInterface $date): string
    {
        $period = $date->format('Y');
        $now = now();

        DB::table('delivery_counters')->insertOrIgnore([
            'scope' => $scope,
            'period' => $period,
            'last_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $counter = DB::table('delivery_counters')
            ->where('scope', $scope)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();
        $nextNumber = ((int) $counter->last_number) + 1;

        DB::table('delivery_counters')
            ->where('scope', $scope)
            ->where('period', $period)
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => $now,
            ]);

        return sprintf('%s-%s-%06d', mb_strtoupper($prefix), $period, $nextNumber);
    }
}
