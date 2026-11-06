<?php

namespace Database\Factories;

use App\Enums\PipelineStatus;
use App\Enums\StatusEventSource;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StatusEvent>
 */
class StatusEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'from_status' => PipelineStatus::Applied,
            'to_status' => PipelineStatus::Interview,
            'source' => StatusEventSource::Manual,
            'evidence' => fake()->optional()->sentence(),
            'email_id' => null,
            'happened_at' => fake()->dateTimeBetween('-7 days'),
        ];
    }
}
