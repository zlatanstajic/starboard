<?php

namespace App\Exceptions\NetworkProfile;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a network profile is not found.
 *
 * @package App\Exceptions\NetworkProfile
 */
final class NetworkProfileNotFoundException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            __('messages.network_profile.not_found'),
            Response::HTTP_NOT_FOUND
        );
    }
}
