<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\FilterList;

use App\Exceptions\FilterList\FilterListDeletionFailedException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class FilterListDeletionFailedExceptionTest extends TestCase
{
    public function test_message_and_code_are_localized(): void
    {
        $exception = new FilterListDeletionFailedException;

        $this->assertSame(__('messages.filter_list.deletion_failed'), $exception->getMessage());
        $this->assertSame(Response::HTTP_CONFLICT, $exception->getCode());
    }
}
