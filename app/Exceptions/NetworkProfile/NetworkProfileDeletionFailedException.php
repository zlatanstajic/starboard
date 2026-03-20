<?php

declare(strict_types=1);

namespace App\Exceptions\NetworkProfile;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a network profile deletion operation fails.
 */
final class NetworkProfileDeletionFailedException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            __('messages.network_profile.deletion_failed'),
            Response::HTTP_CONFLICT
        );
    }
}
