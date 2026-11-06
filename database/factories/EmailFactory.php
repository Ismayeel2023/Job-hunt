<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Email>
 */
class EmailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gmail_account' => fake()->safeEmail(),
            'gmail_message_id' => fake()->unique()->regexify('[a-f0-9]{16}'),
            'thread_id' => fake()->regexify('[a-f0-9]{16}'),
            'from' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'snippet' => fake()->sentence(),
            'received_at' => fake()->dateTimeBetween('-7 days'),
            'matched_application_id' => null,
            'classification' => null,
            'processed' => false,
        ];
    }
}
