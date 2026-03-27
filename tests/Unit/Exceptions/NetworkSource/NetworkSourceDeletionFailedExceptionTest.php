<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkSource;

use App\Exceptions\NetworkSource\NetworkSourceDeletionFailedException;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkSourceDeletionFailedExceptionTest extends TestCase
{
    public function test_exception_extends_base_exception(): void
    {
        $exception = new NetworkSourceDeletionFailedException;

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_message_matches_translation(): void
    {
        $exception = new NetworkSourceDeletionFailedException;

        $this->assertSame(
            __('messages.network_source.deletion_failed'),
            $exception->getMessage()
        );
    }

    public function test_code_is_http_conflict(): void
    {
        $exception = new NetworkSourceDeletionFailedException;

        $this->assertSame(Response::HTTP_CONFLICT, $exception->getCode());
    }
}
