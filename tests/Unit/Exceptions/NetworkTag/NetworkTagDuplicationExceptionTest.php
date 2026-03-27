<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkTag;

use App\Exceptions\NetworkTag\NetworkTagDuplicationException;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkTagDuplicationExceptionTest extends TestCase
{
    public function test_exception_extends_base_exception(): void
    {
        $exception = new NetworkTagDuplicationException('duplicate_name');

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_message_contains_name(): void
    {
        $name = 'duplicate_name';
        $exception = new NetworkTagDuplicationException($name);

        $this->assertSame(
            __('messages.network_tag.duplication', compact('name')),
            $exception->getMessage()
        );
    }

    public function test_code_is_http_conflict(): void
    {
        $exception = new NetworkTagDuplicationException('duplicate_name');

        $this->assertSame(Response::HTTP_CONFLICT, $exception->getCode());
    }
}
