<?php

namespace Database\Factories;

use App\Enums\RoundType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\InterviewQuestion>
 */
class InterviewQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'round_type' => fake()->randomElement(RoundType::cases()),
            'question' => fake()->sentence().'?',
            'topic' => fake()->randomElement(['SQL', 'OOP', 'System design', 'Behavioural']),
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'year_reported' => fake()->numberBetween(2022, 2026),
            'source_url' => fake()->url(),
            'source_name' => fake()->randomElement(['Glassdoor', 'GeeksforGeeks', 'AmbitionBox']),
            'suggested_answer' => fake()->paragraph(),
            'answer_based_on' => ['projects' => [], 'experience' => []],
            'practiced' => false,
            'my_notes' => null,
        ];
    }
}
