<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class SetLocaleMiddlewareTest extends TestCase
{
    public function test_invalid_session_locale_falls_back_to_default()
    {
        // Put an invalid locale in session
        $response = $this->withSession(['locale' => 'fr'])->get('/');

        $response->assertOk();

        // The landing page contains the 'welcome' message in English by default
        $response->assertSee('Starboard');
    }
}
