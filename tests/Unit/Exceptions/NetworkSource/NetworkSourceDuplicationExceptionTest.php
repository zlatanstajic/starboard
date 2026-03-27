<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkSource;

use App\Exceptions\NetworkSource\NetworkSourceDuplicationException;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkSourceDuplicationExceptionTest extends TestCase
{
    public function test_exception_extends_base_exception(): void
    {
        $exception = new NetworkSourceDuplicationException('TestSource', 'https://example.com');

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_message_contains_name_and_url(): void
    {
        $name = 'TestSource';
        $url = 'https://example.com';
        $exception = new NetworkSourceDuplicationException($name, $url);

        $this->assertSame(
            __('messages.network_source.duplication', compact('name', 'url')),
            $exception->getMessage()
        );
    }

    public function test_code_is_http_conflict(): void
    {
        $exception = new NetworkSourceDuplicationException('TestSource', 'https://example.com');

        $this->assertSame(Response::HTTP_CONFLICT, $exception->getCode());
    }
}
