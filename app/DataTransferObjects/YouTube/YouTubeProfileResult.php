<?php

declare(strict_types=1);

namespace App\DataTransferObjects\YouTube;

use App\Enums\YouTubeFetchOutcome;

final readonly class YouTubeProfileResult
{
    public function __construct(
        public YouTubeFetchOutcome $outcome,
        public string $stage,
        public int $requestCount = 0,
        public ?int $status = null,
        public ?string $transport = null,
        public int $durationMilliseconds = 0,
        public ?int $retryAfterSeconds = null,
        public ?string $error = null,
    ) {}
}
