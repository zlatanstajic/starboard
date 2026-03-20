<?php

declare(strict_types=1);

namespace App\Exceptions\NetworkTag;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a network tag deletion operation fails.
 */
final class NetworkTagDeletionFailedException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            __('messages.network_tag.deletion_failed'),
            Response::HTTP_CONFLICT
        );
    }
}
