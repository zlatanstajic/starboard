<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use App\Contracts\YouTube\YouTubeTransport;
use App\DataTransferObjects\YouTube\YouTubeFetchRequest;
use App\DataTransferObjects\YouTube\YouTubeFetchResult;
use App\Exceptions\YouTube\YouTubeTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Throwable;

final class LaravelHttpYouTubeTransport implements YouTubeTransport
{
    /** @var list<string> */
    private const array ALLOWED_PATHS = [
        '/youtube/v3/channels',
        '/youtube/v3/playlistItems',
    ];

    public function fetch(YouTubeFetchRequest $request): YouTubeFetchResult
    {
        $apiKey = config('youtube.api_key');

        throw_if(! is_string($apiKey) || trim($apiKey) === '', YouTubeTransportException::class, 'The YouTube Data API key is not configured.');

        throw_if($this->containsCredentialQueryParameter($request), YouTubeTransportException::class, 'YouTube API credentials must not be included in request URLs.');

        throw_unless($this->isAllowedApiUrl($request->url), YouTubeTransportException::class, 'The YouTube Data API request URL is not allowed.');

        $startedAt = hrtime(true);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Starboard YouTube Data API Fetcher',
                'Accept' => 'application/json',
                'X-Goog-Api-Key' => trim($apiKey),
            ])->connectTimeout((int) config('youtube.connect_timeout'))
                ->timeout((int) config('youtube.request_timeout'))
                ->withOptions(['allow_redirects' => false])
                ->get($request->url, $request->query);
        } catch (ConnectionException $exception) {
            throw new YouTubeTransportException('The YouTube connection failed.', previous: $exception);
        } catch (Throwable $exception) {
            throw new YouTubeTransportException('The YouTube request failed before receiving a response.', previous: $exception);
        }

        $body = $response->body();

        throw_if(strlen($body) > (int) config('youtube.max_response_bytes'), YouTubeTransportException::class, 'The YouTube response exceeded the configured size limit.');

        $headers = [];

        foreach ($response->headers() as $name => $values) {
            $headers[strtolower((string) $name)] = array_values(array_map(strval(...), $values));
        }

        return new YouTubeFetchResult(
            status: $response->status(),
            body: $body,
            headers: $headers,
            effectiveUri: (string) ($response->effectiveUri() ?? $request->url),
            transport: 'laravel-http',
            durationMilliseconds: (int) round((hrtime(true) - $startedAt) / 1_000_000),
            physicalRequestCount: 1,
            retryAfterSeconds: $this->parseRetryAfter($response->header('Retry-After')),
        );
    }

    private function parseRetryAfter(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (ctype_digit(trim($value))) {
            $seconds = (int) trim($value);
        } else {
            try {
                $seconds = max(0, Date::parse($value)->getTimestamp() - Date::now()->getTimestamp());
            } catch (Throwable) {
                return null;
            }
        }

        return min($seconds, (int) config('youtube.retry.max_retry_after'));
    }

    private function isAllowedApiUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === 'www.googleapis.com'
            && isset($parts['path'])
            && in_array($parts['path'], self::ALLOWED_PATHS, true)
            && ! isset($parts['user'], $parts['pass'], $parts['port'], $parts['query'], $parts['fragment']);
    }

    private function containsCredentialQueryParameter(YouTubeFetchRequest $request): bool
    {
        foreach (array_keys($request->query) as $name) {
            if (strtolower($name) === 'key') {
                return true;
            }
        }

        $queryString = parse_url($request->url, PHP_URL_QUERY);

        if (! is_string($queryString)) {
            return false;
        }

        parse_str($queryString, $urlQuery);

        foreach (array_keys($urlQuery) as $name) {
            if (strtolower((string) $name) === 'key') {
                return true;
            }
        }

        return false;
    }
}
