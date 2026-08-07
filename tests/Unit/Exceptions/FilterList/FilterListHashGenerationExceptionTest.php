<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\FilterList;

use App\Exceptions\FilterList\FilterListHashGenerationException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class FilterListHashGenerationExceptionTest extends TestCase
{
    public function test_message_and_code_are_localized(): void
    {
        $exception = new FilterListHashGenerationException;

        $this->assertSame(__('messages.filter_list.hash_generation_failed'), $exception->getMessage());
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getCode());
    }
}
