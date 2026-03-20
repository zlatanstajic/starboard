<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_is_not_rate_limited_throws_validation_exception(): void
    {
        Event::fake();

        $request = new LoginRequest;
        $request->setMethod('POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->merge(['email' => 'test@example.com', 'password' => 'password']);

        $throttleKey = 'test@example.com|127.0.0.1';

        // Simulate 5 failed attempts to trigger rate limit
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey);
        }

        $this->expectException(ValidationException::class);

        $request->ensureIsNotRateLimited();
    }

    public function test_ensure_is_not_rate_limited_dispatches_lockout_event(): void
    {
        Event::fake();

        $request = new LoginRequest;
        $request->setMethod('POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->merge(['email' => 'test@example.com', 'password' => 'password']);

        $throttleKey = 'test@example.com|127.0.0.1';

        // Simulate 5 failed attempts to trigger rate limiting
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey);
        }

        try {
            $request->ensureIsNotRateLimited();
        } catch (ValidationException) {
            // Expected exception
        }

        Event::assertDispatched(Lockout::class);
    }

    public function test_ensure_is_not_rate_limited_does_not_throw_when_under_limit(): void
    {
        $request = new LoginRequest;
        $request->setMethod('POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.2');
        $request->merge(['email' => 'test2@example.com', 'password' => 'password']);

        // Clear any existing rate limit
        RateLimiter::clear('test2@example.com|127.0.0.2');

        // Should not throw exception
        $this->expectNotToPerformAssertions();
        $request->ensureIsNotRateLimited();
    }

    public function test_validation_exception_contains_throttle_message(): void
    {
        Event::fake();

        $request = new LoginRequest;
        $request->setMethod('POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->merge(['email' => 'test@example.com', 'password' => 'password']);

        $throttleKey = 'test@example.com|127.0.0.1';

        // Simulate 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey);
        }

        try {
            $request->ensureIsNotRateLimited();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            // Check that the error message contains timing information
            $errorMessage = $e->errors()['email'][0];
            $this->assertStringContainsString('try again', mb_strtolower((string) $errorMessage));
        }
    }
}
