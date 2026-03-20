<?php

declare(strict_types=1);

namespace App\Exceptions\NetworkSource;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a network source duplication occurs.
 */
final class NetworkSourceDuplicationException extends Exception
{
    /**
     * Construct the exception.
     */
    public function __construct(string $name, string $url)
    {
        parent::__construct(
            __('messages.network_source.duplication', ['name' => $name, 'url' => $url]),
            Response::HTTP_CONFLICT
        );
    }
}
