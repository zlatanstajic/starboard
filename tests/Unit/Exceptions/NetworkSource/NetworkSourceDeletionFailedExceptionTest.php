<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkSource;

use App\Exceptions\NetworkSource\NetworkSourceDeletionFailedException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkSourceDeletionFailedExceptionTest extends TestCase
{
    public function test_exception_message_and_code(): void
    {
        $exception = new NetworkSourceDeletionFailedException;

        $this->assertEquals(
            __('messages.network_source.deletion_failed'),
            $exception->getMessage()
        );
        $this->assertEquals(
            Response::HTTP_CONFLICT,
            $exception->getCode()
        );
    }
}
