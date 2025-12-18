<?php

namespace App\Exceptions\User;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a user is not authorized to perform operations.
 *
 * @package App\Exceptions\User
 */
final class UserOperationsUnauthorizedException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            __('messages.user.operations_unauthorized'),
            Response::HTTP_FORBIDDEN
        );
    }
}
