<?php

declare(strict_types=1);

namespace App\Exceptions\FilterList;

use Exception;
use Symfony\Component\HttpFoundation\Response;

final class FilterListDeletionFailedException extends Exception
{
    public function __construct()
    {
        parent::__construct(
            __('messages.filter_list.deletion_failed'),
            Response::HTTP_CONFLICT
        );
    }
}
