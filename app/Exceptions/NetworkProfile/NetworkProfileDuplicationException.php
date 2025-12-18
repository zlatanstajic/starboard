<?php

namespace App\Exceptions\NetworkProfile;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a network profile duplication occurs.
 *
 * @package App\Exceptions\NetworkProfile
 */
final class NetworkProfileDuplicationException extends Exception
{
    /**
     * Construct the exception.
     *
     * @param string $username The username that caused the duplication.
     */
    public function __construct(string $username)
    {
        parent::__construct(
            __('messages.network_profile.duplication', ['username' => $username]),
            Response::HTTP_CONFLICT
        );
    }
}
