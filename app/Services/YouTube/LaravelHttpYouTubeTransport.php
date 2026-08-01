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
    public function fetch(YouTubeFetchRequest $request): YouTubeFetchResult
    {
        $startedAt = hrtime(true);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Starboard YouTube Feed Fetcher',
                'Accept' => 'application/atom+xml, application/xml;q=0.9, text/html;q=0.8',
                'Accept-Language' => 'en',
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
}
