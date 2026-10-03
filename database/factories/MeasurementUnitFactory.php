<?php

namespace Database\Factories;

use App\Models\MeasurementUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeasurementUnit> */
class MeasurementUnitFactory extends Factory
{
    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('####');

        return [
            'name' => "Unidad {$suffix}",
            'symbol' => "u{$suffix}",
            'decimal_places' => 0,
            'is_active' => true,
        ];
    }
}
