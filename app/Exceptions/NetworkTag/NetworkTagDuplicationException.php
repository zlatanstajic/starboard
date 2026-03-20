<?php

declare(strict_types=1);

namespace App\Exceptions\NetworkTag;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a network tag duplication occurs.
 */
final class NetworkTagDuplicationException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct(string $name)
    {
        parent::__construct(
            __('messages.network_tag.duplication', ['name' => $name]),
            Response::HTTP_CONFLICT
        );
    }
}
