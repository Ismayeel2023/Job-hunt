<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_backend_visits_are_sent_to_the_frontend(): void
    {
        $this->get('/')->assertRedirect(config('app.frontend_url'));
        $this->get('/login')->assertRedirect(config('app.frontend_url').'/login');
    }
}
