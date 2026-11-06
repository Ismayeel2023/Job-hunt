<?php

namespace Database\Factories;

use App\Enums\JobPlatform;
use App\Enums\PipelineStatus;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Job>
 */
class JobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->jobTitle(),
            'platform' => fake()->randomElement(JobPlatform::cases()),
            'url' => fake()->unique()->url(),
            'location' => fake()->city(),
            'experience_range' => fake()->randomElement(['0-2 yrs', '2-4 yrs', '3-5 yrs', '5-8 yrs']),
            'salary_text' => fake()->optional()->randomElement(['6-10 LPA', '12-18 LPA', 'Not disclosed']),
            'jd_text' => fake()->paragraphs(3, true),
            'must_have_skills' => ['PHP', 'Laravel', 'SQL'],
            'nice_to_have_skills' => ['React', 'Docker'],
            'match_score' => fake()->numberBetween(0, 100),
            'matched_skills' => ['PHP', 'Laravel'],
            'gaps' => ['Docker'],
            'found_at' => fake()->dateTimeBetween('-30 days'),
            'status' => PipelineStatus::Found,
        ];
    }
}
