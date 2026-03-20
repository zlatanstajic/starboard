<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkTag;

use App\Exceptions\NetworkTag\NetworkTagDeletionFailedException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkTagDeletionFailedExceptionTest extends TestCase
{
    public function test_exception_message_and_code(): void
    {
        $exception = new NetworkTagDeletionFailedException;

        $this->assertEquals(
            __('messages.network_tag.deletion_failed'),
            $exception->getMessage()
        );
        $this->assertEquals(
            Response::HTTP_CONFLICT,
            $exception->getCode()
        );
    }
}
