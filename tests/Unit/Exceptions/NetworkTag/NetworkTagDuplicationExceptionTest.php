<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\NetworkTag;

use App\Exceptions\NetworkTag\NetworkTagDuplicationException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NetworkTagDuplicationExceptionTest extends TestCase
{
    public function test_exception_has_expected_message_and_status_code(): void
    {
        $name = 'duplicate_name';
        $exception = new NetworkTagDuplicationException($name);

        $this->assertInstanceOf(
            NetworkTagDuplicationException::class,
            $exception
        );

        $this->assertEquals(
            __('messages.network_tag.duplication', compact('name')),
            $exception->getMessage()
        );

        $this->assertEquals(Response::HTTP_CONFLICT, $exception->getCode());
    }
}
