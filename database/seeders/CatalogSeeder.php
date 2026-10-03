<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Unidad', 'symbol' => 'u', 'decimal_places' => 0],
            ['name' => 'Kilogramo', 'symbol' => 'kg', 'decimal_places' => 3],
            ['name' => 'Gramo', 'symbol' => 'g', 'decimal_places' => 3],
            ['name' => 'Litro', 'symbol' => 'L', 'decimal_places' => 3],
            ['name' => 'Mililitro', 'symbol' => 'ml', 'decimal_places' => 3],
        ];

        foreach ($units as $unit) {
            $measurementUnit = MeasurementUnit::withTrashed()->firstOrNew(['symbol' => $unit['symbol']]);
            $measurementUnit->fill([...$unit, 'is_active' => true]);
            $measurementUnit->deleted_at = null;
            $measurementUnit->save();
        }
    }
}
