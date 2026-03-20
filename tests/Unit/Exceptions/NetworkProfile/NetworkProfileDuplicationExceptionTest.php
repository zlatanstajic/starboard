<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkProfile;

use App\Exceptions\NetworkProfile\NetworkProfileDuplicationException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkProfileDuplicationExceptionTest extends TestCase
{
    public function test_exception_has_expected_message_and_status_code(): void
    {
        $username = 'fake_username';
        $exception = new NetworkProfileDuplicationException($username);

        $this->assertInstanceOf(
            NetworkProfileDuplicationException::class,
            $exception
        );
        $this->assertEquals(
            __('messages.network_profile.duplication', compact('username')),
            $exception->getMessage()
        );
        $this->assertEquals(Response::HTTP_CONFLICT, $exception->getCode());
    }
}
