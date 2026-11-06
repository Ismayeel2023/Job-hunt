<?php

namespace Database\Factories;

use App\Services\Gmail\GoogleOAuth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\GmailAccount>
 */
class GmailAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'refresh_token' => 'refresh-'.fake()->uuid(),
            'access_token' => 'access-'.fake()->uuid(),
            'access_token_expires_at' => now()->addHour(),
            'scopes' => GoogleOAuth::SCOPE,
            'needs_reconnect' => false,
        ];
    }
}
