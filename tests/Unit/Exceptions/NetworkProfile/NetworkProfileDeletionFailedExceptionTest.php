<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkProfile;

use App\Exceptions\NetworkProfile\NetworkProfileDeletionFailedException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkProfileDeletionFailedExceptionTest extends TestCase
{
    public function test_exception_has_expected_message_and_status_code(): void
    {
        $exception = new NetworkProfileDeletionFailedException;

        $this->assertInstanceOf(NetworkProfileDeletionFailedException::class, $exception);
        $this->assertEquals(__('messages.network_profile.deletion_failed'), $exception->getMessage());
        $this->assertEquals(Response::HTTP_CONFLICT, $exception->getCode());
    }
}
