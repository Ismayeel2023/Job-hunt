<?php

namespace Database\Factories;

use App\Enums\PipelineStatus;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Application>
 */
class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'resume_version_id' => null,
            'applied_at' => fake()->dateTimeBetween('-14 days'),
            'applied_via' => fake()->randomElement(['Easy Apply', 'Company portal', 'Referral']),
            'cover_note' => fake()->optional()->paragraph(),
            'screening_answers' => [['question' => 'Notice period?', 'answer' => '30 days']],
            'status' => PipelineStatus::Applied,
            'next_step' => null,
            'next_step_at' => null,
        ];
    }
}
