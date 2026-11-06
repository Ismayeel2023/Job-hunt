<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_goes_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
    }
}
