<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commercial_name' => fake()->company(),
            'legal_name' => fake()->company().' S. de R.L.',
            'rtn' => fake()->numerify('##############'),
            'phone' => fake()->numerify('####-####'),
            'whatsapp' => fake()->numerify('####-####'),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'logo_path' => null,
            'slogan' => fake()->sentence(5),
        ];
    }
}
