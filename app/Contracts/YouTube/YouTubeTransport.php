<?php

declare(strict_types=1);

namespace App\Contracts\YouTube;

use App\DataTransferObjects\YouTube\YouTubeFetchRequest;
use App\DataTransferObjects\YouTube\YouTubeFetchResult;

interface YouTubeTransport
{
    public function fetch(YouTubeFetchRequest $request): YouTubeFetchResult;
}
