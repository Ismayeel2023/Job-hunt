<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requests from the React app on port 5180 count as "stateful", so Sanctum gives them a session.
     */
    private function fromFrontend(): static
    {
        return $this->withHeaders(['Referer' => 'http://127.0.0.1:5180/', 'Origin' => 'http://127.0.0.1:5180']);
    }

    public function test_the_frontend_can_log_in_and_out_with_a_session_cookie(): void
    {
        $user = User::factory()->create(['email' => 'me@jobhunt.test', 'password' => Hash::make('secret-password')]);

        $this->fromFrontend()->getJson('/api/app/user')->assertUnauthorized();

        $this->fromFrontend()->postJson('/api/app/login', ['email' => 'me@jobhunt.test', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('id', $user->id);
        $this->assertAuthenticatedAs($user);

        $this->fromFrontend()->getJson('/api/app/user')->assertOk()->assertJsonPath('email', 'me@jobhunt.test');

        $this->fromFrontend()->postJson('/api/app/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'me@jobhunt.test', 'password' => Hash::make('secret-password')]);

        $this->fromFrontend()->postJson('/api/app/login', ['email' => 'me@jobhunt.test', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_profile_and_password_can_be_changed(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->patchJson('/api/app/profile', ['name' => 'New', 'email' => 'new@example.com'])->assertUnauthorized();

        $this->actingAs($user)->patchJson('/api/app/profile', ['name' => 'New', 'email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('email', 'new@example.com');

        $this->putJson('/api/app/password', ['current_password' => 'nope', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->putJson('/api/app/password', ['current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertNoContent();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_only_the_frontend_may_call_the_api_from_a_browser(): void
    {
        // With one allowed origin the header always names it, so a browser on any other site is refused.
        $outside = $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'GET'])
            ->options('/api/app/user');
        $this->assertNotSame('https://evil.example', $outside->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $outside->headers->get('Access-Control-Allow-Origin'));

        $this->withHeaders(['Origin' => config('app.frontend_url'), 'Access-Control-Request-Method' => 'GET'])
            ->options('/api/app/user')
            ->assertHeader('Access-Control-Allow-Origin', config('app.frontend_url'));
    }

    public function test_api_routes_answer_guests_with_401_json_not_a_redirect(): void
    {
        $this->getJson('/api/app/dashboard')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        $this->get('/api/app/dashboard')->assertUnauthorized();
    }
}
