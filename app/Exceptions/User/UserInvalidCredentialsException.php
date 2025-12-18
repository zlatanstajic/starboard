<?php

namespace App\Exceptions\User;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when invalid user credentials are provided.
 *
 * @package App\Exceptions\User
 */
final class UserInvalidCredentialsException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            __('messages.user.invalid_credentials'),
            Response::HTTP_UNAUTHORIZED
        );
    }
}
