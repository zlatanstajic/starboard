<?php

namespace App\Exceptions\User;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a user duplication occurs.
 *
 * @package App\Exceptions\User
 */
final class UserDuplicationException extends Exception
{
    /**
     * Construct the exception.
     *
     * @param string $email
     */
    public function __construct(string $email)
    {
        parent::__construct(
            __('messages.user.duplication', ['email' => $email]),
            Response::HTTP_CONFLICT
        );
    }
}
