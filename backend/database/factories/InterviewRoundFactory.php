<?php

namespace Database\Factories;

use App\Enums\RoundType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\InterviewRound>
 */
class InterviewRoundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'round_name' => fake()->randomElement(['Online assessment', 'Technical round 1', 'HR discussion']),
            'round_type' => fake()->randomElement(RoundType::cases()),
            'description' => fake()->sentence(),
            'typical_duration' => fake()->randomElement(['30 min', '45 min', '60 min']),
            'source_url' => fake()->url(),
        ];
    }
}
