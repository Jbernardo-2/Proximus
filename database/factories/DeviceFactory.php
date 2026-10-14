<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'installation_id' => (string) Str::uuid(),
            'code' => 'M'.fake()->unique()->numerify('###'),
            'name' => fake()->randomElement(['Samsung A15', 'Moto G24', 'Redmi 13C']),
            'platform' => 'android',
            'app_version' => '1.0.0',
            'user_id' => User::factory(),
            'last_seen_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
