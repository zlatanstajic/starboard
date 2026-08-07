<?php

declare(strict_types=1);

namespace App\Exceptions\FilterList;

use Exception;
use Symfony\Component\HttpFoundation\Response;

final class FilterListHashGenerationException extends Exception
{
    public function __construct()
    {
        parent::__construct(
            __('messages.filter_list.hash_generation_failed'),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }
}
