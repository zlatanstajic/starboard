<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkProfile;

use App\Exceptions\NetworkProfile\NetworkProfileDuplicationException;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkProfileDuplicationExceptionTest extends TestCase
{
    public function test_exception_extends_base_exception(): void
    {
        $exception = new NetworkProfileDuplicationException('fake_username');

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_message_contains_username(): void
    {
        $username = 'fake_username';
        $exception = new NetworkProfileDuplicationException($username);

        $this->assertSame(
            __('messages.network_profile.duplication', compact('username')),
            $exception->getMessage()
        );
    }

    public function test_code_is_http_conflict(): void
    {
        $exception = new NetworkProfileDuplicationException('fake_username');

        $this->assertSame(Response::HTTP_CONFLICT, $exception->getCode());
    }
}
