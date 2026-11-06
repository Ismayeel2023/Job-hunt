<?php

namespace Database\Factories;

use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ResumeVersion>
 */
class ResumeVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'label' => 'Tailored for '.fake()->company(),
            'tex_source' => '\documentclass{article}\begin{document}'.fake()->sentence().'\end{document}',
            'pdf_path' => null,
            'keywords_covered' => ['Laravel', 'SQL'],
            'changes_summary' => ['added' => ['Laravel'], 'removed' => [], 'reordered' => ['Skills']],
            'is_base' => false,
        ];
    }

    public function base(): static
    {
        return $this->state(fn () => [
            'job_id' => null,
            'label' => 'Base resume',
            'changes_summary' => null,
            'is_base' => true,
        ]);
    }
}
