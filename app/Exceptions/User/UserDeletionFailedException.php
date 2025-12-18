<?php

namespace App\Exceptions\User;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a user deletion operation fails.
 *
 * @package App\Exceptions\User
 */
final class UserDeletionFailedException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            __('messages.user.deletion_failed'),
            Response::HTTP_CONFLICT
        );
    }
}
