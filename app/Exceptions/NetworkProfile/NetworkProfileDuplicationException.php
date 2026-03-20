<?php

declare(strict_types=1);

namespace App\Exceptions\NetworkProfile;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a network profile duplication occurs.
 */
final class NetworkProfileDuplicationException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct(string $username)
    {
        parent::__construct(
            __('messages.network_profile.duplication', ['username' => $username]),
            Response::HTTP_CONFLICT
        );
    }
}
