<?php

declare(strict_types=1);

namespace Tests\Unit\Services\YouTube;

use App\Contracts\YouTube\YouTubeTransport;
use App\DataTransferObjects\YouTube\YouTubeFetchRequest;
use App\Exceptions\YouTube\YouTubeDisabledException;
use App\Exceptions\YouTube\YouTubeTransportException;
use App\Services\YouTube\LaravelHttpYouTubeTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Override;
use Tests\TestCase;

class LaravelHttpYouTubeTransportTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('youtube.api_key', 'secret-server-key');
    }

    public function test_sends_api_key_only_in_header_with_exact_json_request_metadata(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => []])]);

        $result = (new LaravelHttpYouTubeTransport)->fetch(new YouTubeFetchRequest(
            'https://www.googleapis.com/youtube/v3/channels',
            ['part' => 'contentDetails', 'id' => 'UC123'],
        ));

        $this->assertSame(200, $result->status);
        $this->assertSame(1, $result->physicalRequestCount);
        $this->assertSame('laravel-http', $result->transport);
        Http::assertSent(function (Request $request): bool {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && $request->header('Accept') === ['application/json']
                && $request->header('X-Goog-Api-Key') === ['secret-server-key']
                && $request->header('User-Agent') === ['Starboard YouTube Data API Fetcher']
                && $request->header('Cookie') === []
                && ! str_contains($request->url(), 'secret-server-key')
                && ! array_key_exists('key', $query)
                && $query === ['part' => 'contentDetails', 'id' => 'UC123'];
        });
    }

    public function test_missing_api_key_fails_closed_without_sending_a_request(): void
    {
        config()->set('youtube.api_key', null);
        Http::preventStrayRequests();
        Http::fake();

        $this->expectException(YouTubeTransportException::class);
        $this->expectExceptionMessage('The YouTube Data API key is not configured.');

        try {
            (new LaravelHttpYouTubeTransport)->fetch(new YouTubeFetchRequest('https://www.googleapis.com/youtube/v3/channels'));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_rejects_credentials_in_query_parameters_without_sending_a_request(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        foreach ([
            new YouTubeFetchRequest('https://www.googleapis.com/youtube/v3/channels', ['key' => 'query-secret']),
            new YouTubeFetchRequest('https://www.googleapis.com/youtube/v3/channels?KEY=url-secret'),
        ] as $request) {
            try {
                (new LaravelHttpYouTubeTransport)->fetch($request);
                $this->fail('Expected credential-bearing request to fail closed.');
            } catch (YouTubeTransportException $exception) {
                $this->assertSame('YouTube API credentials must not be included in request URLs.', $exception->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_rejects_requests_outside_the_fixed_data_api_endpoints(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        foreach ([
            'http://www.googleapis.com/youtube/v3/channels',
            'https://googleapis.com/youtube/v3/channels',
            'https://www.googleapis.com/youtube/v3/search',
            'https://example.com/youtube/v3/channels',
        ] as $url) {
            try {
                (new LaravelHttpYouTubeTransport)->fetch(new YouTubeFetchRequest($url));
                $this->fail('Expected an unapproved Data API URL to fail closed.');
            } catch (YouTubeTransportException $exception) {
                $this->assertSame('The YouTube Data API request URL is not allowed.', $exception->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_returns_normalized_metadata_and_delta_retry_after_without_retrying(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/*' => Http::response('limited', 429, [
            'Retry-After' => '120',
            'X-Signal' => 'safe',
        ])]);

        $result = (new LaravelHttpYouTubeTransport)->fetch(new YouTubeFetchRequest('https://www.googleapis.com/youtube/v3/channels'));

        $this->assertSame(429, $result->status);
        $this->assertSame('safe', $result->header('X-Signal'));
        $this->assertSame(120, $result->retryAfterSeconds);
        Http::assertSentCount(1);
    }

    public function test_does_not_follow_redirects(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/*' => Http::response('', 302, [
            'Location' => 'https://example.invalid/credential-target',
        ])]);

        $result = (new LaravelHttpYouTubeTransport)->fetch(new YouTubeFetchRequest('https://www.googleapis.com/youtube/v3/channels'));

        $this->assertSame(302, $result->status);
        Http::assertSentCount(1);
    }

    public function test_parses_http_date_and_clamps_retry_after(): void
    {
        Date::setTestNow('2026-08-01 12:00:00 UTC');
        config()->set('youtube.retry.max_retry_after', 90);
        Http::fake(['www.googleapis.com/*' => Http::response('', 503, [
            'Retry-After' => now()->addMinutes(10)->toRfc7231String(),
        ])]);

        $result = (new LaravelHttpYouTubeTransport)->fetch(new YouTubeFetchRequest('https://www.googleapis.com/youtube/v3/channels'));

        $this->assertSame(90, $result->retryAfterSeconds);
    }

    public function test_ignores_malformed_retry_after(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response('', 503, ['Retry-After' => 'later'])]);

        $result = (new LaravelHttpYouTubeTransport)->fetch(new YouTubeFetchRequest('https://www.googleapis.com/youtube/v3/channels'));

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
