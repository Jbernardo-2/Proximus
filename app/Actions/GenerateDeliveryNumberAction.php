<?php

namespace App\Actions;

use App\Models\Device;
use App\Services\OfflineSync;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateDeliveryNumberAction
{
    public function __construct(private OfflineSync $offline) {}

    /**
     * @param  string|null  $deviceNumber  Number already printed by a phone while offline; kept when it belongs to that phone's series.
     */
    public function handle(string $scope, string $prefix, CarbonInterface $date, ?string $deviceNumber = null): string
    {
        if ($deviceNumber !== null && $this->acceptsDeviceNumber($scope, $deviceNumber)) {
            return $deviceNumber;
        }

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

    private function acceptsDeviceNumber(string $scope, string $number): bool
    {
        $device = $this->offline->device();

        if ($device === null || ! $device->ownsNumber($scope, $number)) {
            return false;
        }

        [, $table, $column] = Device::SERIES[$scope];

        return DB::table($table)->where($column, $number)->doesntExist();
    }
}
