<?php

declare(strict_types=1);

namespace App\Exceptions\NetworkSource;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a network source deletion operation fails.
 */
final class NetworkSourceDeletionFailedException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            __('messages.network_source.deletion_failed'),
            Response::HTTP_CONFLICT
        );
    }
}
