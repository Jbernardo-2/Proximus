<?php

namespace App\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateOrderNumberAction
{
    public function handle(CarbonInterface $orderDate): string
    {
        $period = $orderDate->format('Y');
        $now = now();

        DB::table('order_counters')->insertOrIgnore([
            'period' => $period,
            'last_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $counter = DB::table('order_counters')
            ->where('period', $period)
            ->lockForUpdate()
            ->first();
        $nextNumber = ((int) $counter->last_number) + 1;

        DB::table('order_counters')
            ->where('period', $period)
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => $now,
            ]);

        return sprintf(
            '%s-%s-%06d',
            mb_strtoupper((string) config('proximus.order_number_prefix', 'PED')),
            $period,
            $nextNumber,
        );
    }
}
