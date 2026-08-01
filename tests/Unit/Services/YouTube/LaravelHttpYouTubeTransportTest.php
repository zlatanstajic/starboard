<?php

declare(strict_types=1);

namespace Tests\Unit\Services\YouTube;

use App\Contracts\YouTube\YouTubeTransport;
use App\DataTransferObjects\YouTube\YouTubeFetchRequest;
use App\Exceptions\YouTube\YouTubeDisabledException;
use App\Services\YouTube\LaravelHttpYouTubeTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LaravelHttpYouTubeTransportTest extends TestCase
{
    public function test_returns_normalized_metadata_and_delta_retry_after(): void
    {
        Http::preventStrayRequests();
        Http::fake(['youtube.com/*' => Http::response('limited', 429, [
            'Retry-After' => '120',
            'X-Signal' => 'safe',
        ])]);

        $result = (new LaravelHttpYouTubeTransport)->fetch(new YouTubeFetchRequest('https://youtube.com/test'));

        $this->assertSame(429, $result->status);
        $this->assertSame('safe', $result->header('X-Signal'));
        $this->assertSame(120, $result->retryAfterSeconds);
        $this->assertSame(1, $result->physicalRequestCount);
        $this->assertSame('laravel-http', $result->transport);
        Http::assertSent(fn (Request $request): bool => $request->header('Cookie') === []
            && $request->header('User-Agent') === ['Starboard YouTube Feed Fetcher']);
    }

    public function test_parses_http_date_and_clamps_retry_after(): void
    {
        Date::setTestNow('2026-08-01 12:00:00 UTC');
        config()->set('youtube.retry.max_retry_after', 90);
        Http::fake(['youtube.com/*' => Http::response('', 503, [
            'Retry-After' => now()->addMinutes(10)->toRfc7231String(),
        ])]);

        $result = (new LaravelHttpYouTubeTransport)->fetch(new YouTubeFetchRequest('https://youtube.com/test'));

        $this->assertSame(90, $result->retryAfterSeconds);
    }

    public function test_ignores_malformed_retry_after(): void
    {
        Http::fake(['youtube.com/*' => Http::response('', 503, ['Retry-After' => 'later'])]);

        $result = (new LaravelHttpYouTubeTransport)->fetch(new YouTubeFetchRequest('https://youtube.com/test'));

        $this->assertNull($result->retryAfterSeconds);
    }

    public function test_container_rejects_an_unsupported_transport(): void
    {
        config()->set('youtube.transport', 'unimplemented-browser');

        $this->expectException(YouTubeDisabledException::class);
        $this->expectExceptionMessage('Unsupported YouTube transport [unimplemented-browser].');

        resolve(YouTubeTransport::class);
    }
}
