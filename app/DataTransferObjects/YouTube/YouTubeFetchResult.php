<?php

declare(strict_types=1);

namespace App\DataTransferObjects\YouTube;

final readonly class YouTubeFetchResult
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers,
        public string $effectiveUri,
        public string $transport,
        public int $durationMilliseconds,
        public int $physicalRequestCount,
        public ?int $retryAfterSeconds,
    ) {}

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? null;

        return $values === null ? null : implode(', ', $values);
    }
}
