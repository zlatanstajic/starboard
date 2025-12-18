<?php

namespace App\Exceptions\User;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when the provided old password is incorrect.
 *
 * @package App\Exceptions\User
 */
final class UserIncorrectOldPasswordException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct()
    {
        parent::__construct(
            __('messages.user.incorrect_old_password'),
            Response::HTTP_CONFLICT
        );
    }
}
