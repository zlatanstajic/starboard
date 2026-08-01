<?php

declare(strict_types=1);

namespace App\DataTransferObjects\YouTube;

final readonly class YouTubeFetchRequest
{
    /** @param array<string, string> $query */
    public function __construct(public string $url, public array $query = []) {}
}
