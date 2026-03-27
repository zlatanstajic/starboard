<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkTag;

use App\Exceptions\NetworkTag\NetworkTagDeletionFailedException;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkTagDeletionFailedExceptionTest extends TestCase
{
    public function test_exception_extends_base_exception(): void
    {
        $exception = new NetworkTagDeletionFailedException;

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_message_matches_translation(): void
    {
        $exception = new NetworkTagDeletionFailedException;

        $this->assertSame(
            __('messages.network_tag.deletion_failed'),
            $exception->getMessage()
        );
    }

    public function test_code_is_http_conflict(): void
    {
        $exception = new NetworkTagDeletionFailedException;

        $this->assertSame(Response::HTTP_CONFLICT, $exception->getCode());
    }
}
