<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkProfile;

use App\Exceptions\NetworkProfile\NetworkProfileDeletionFailedException;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkProfileDeletionFailedExceptionTest extends TestCase
{
    public function test_exception_extends_base_exception(): void
    {
        $exception = new NetworkProfileDeletionFailedException;

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_message_matches_translation(): void
    {
        $exception = new NetworkProfileDeletionFailedException;

        $this->assertSame(
            __('messages.network_profile.deletion_failed'),
            $exception->getMessage()
        );
    }

    public function test_code_is_http_conflict(): void
    {
        $exception = new NetworkProfileDeletionFailedException;

        $this->assertSame(Response::HTTP_CONFLICT, $exception->getCode());
    }
}
