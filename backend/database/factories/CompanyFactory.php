<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'website' => fake()->url(),
            'logo_url' => fake()->imageUrl(128, 128),
            'location' => fake()->city(),
            'industry' => fake()->randomElement(['Fintech', 'SaaS', 'E-commerce', 'Healthtech', 'Consulting']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
