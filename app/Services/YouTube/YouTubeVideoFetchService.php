<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use App\Contracts\YouTube\YouTubeTransport;
use App\DataTransferObjects\YouTube\YouTubeFetchRequest;
use App\DataTransferObjects\YouTube\YouTubeFetchResult;
use App\DataTransferObjects\YouTube\YouTubeProfileResult;
use App\Enums\YouTubeFetchOutcome;
use App\Exceptions\YouTube\YouTubeRequestBudgetExhaustedException;
use App\Exceptions\YouTube\YouTubeTransportException;
use App\Models\NetworkProfile;
use Illuminate\Support\Facades\Date;
use Throwable;

class YouTubeVideoFetchService
{
    private const string FEED_URL = 'https://www.youtube.com/feeds/videos.xml';

    /** @var list<string> */
    private const array BLOCKED_HOSTS = ['accounts.google.com', 'consent.youtube.com'];

    public function __construct(
        private readonly YouTubeTransport $transport,
        private readonly YouTubeRequestBudget $budget,
    ) {}

    public function fetch(int $profileId, int $userId, string $runUuid): YouTubeProfileResult
    {
        if (! config('youtube.execution_enabled')) {
            return new YouTubeProfileResult(YouTubeFetchOutcome::Disabled, 'configuration');
        }

        $profile = NetworkProfile::query()->withoutGlobalScopes()->with('networkSource')->where('user_id', $userId)->find($profileId);

        if ($profile === null) {
            return new YouTubeProfileResult(YouTubeFetchOutcome::StaleProfile, 'profile');
        }

        $username = $profile->username;
        $sourceUrl = $profile->networkSource?->url;
        $requestCount = 0;
        $duration = 0;
        $channelId = $profile->youtube_channel_id;

        if (! is_string($channelId) || $channelId === '') {
            $url = $profile->profileUrl();

            if (! $this->isSafeYouTubeUrl($url)) {
                return new YouTubeProfileResult(YouTubeFetchOutcome::InvalidUrl, 'channel');
            }

            $channelResponse = $this->requestFollowingSafeRedirects($url, ['hl' => 'en'], $runUuid, $requestCount, $duration);

            if ($channelResponse instanceof YouTubeProfileResult) {
                return $channelResponse;
            }

            if (($failure = $this->classifyResponse($channelResponse, 'channel', $requestCount, $duration)) !== null) {
                return $failure;
            }

            $channelId = $this->extractChannelId($channelResponse->body);

            if ($channelId === null) {
                return new YouTubeProfileResult(YouTubeFetchOutcome::ChannelIdMissing, 'channel', $requestCount, $channelResponse->status, $channelResponse->transport, $duration);
            }

            $profile->update(['youtube_channel_id' => $channelId]);
        }

        $feedResponse = $this->requestFollowingSafeRedirects(self::FEED_URL, ['channel_id' => $channelId], $runUuid, $requestCount, $duration);

        if ($feedResponse instanceof YouTubeProfileResult) {
            return $feedResponse;
        }

        if (($failure = $this->classifyResponse($feedResponse, 'feed', $requestCount, $duration)) !== null) {
            return $failure;
        }

        $publishedTimes = $this->parseFeedPublishedTimes($feedResponse->body);

        if ($publishedTimes === null) {
            return new YouTubeProfileResult(YouTubeFetchOutcome::MalformedFeed, 'feed', $requestCount, $feedResponse->status, $feedResponse->transport, $duration);
        }

        $profile->refresh();
        $profile->load('networkSource');

        if ($profile->username !== $username || $profile->networkSource?->url !== $sourceUrl) {
            return new YouTubeProfileResult(YouTubeFetchOutcome::StaleProfile, 'persist', $requestCount, $feedResponse->status, $feedResponse->transport, $duration);
        }

        $profile->update(['new_items' => $this->countNewItems($publishedTimes, $profile)]);

        return new YouTubeProfileResult(YouTubeFetchOutcome::Success, 'complete', $requestCount, $feedResponse->status, $feedResponse->transport, $duration);
    }

    /** @param array<string, string> $query */
    private function requestFollowingSafeRedirects(string $url, array $query, string $runUuid, int &$requestCount, int &$duration): YouTubeFetchResult|YouTubeProfileResult
    {
        $redirects = 0;

        while (true) {
            try {
                $this->budget->reserve($runUuid);
                $requestCount++;
                $response = $this->transport->fetch(new YouTubeFetchRequest($url, $query));
                $duration += $response->durationMilliseconds;
            } catch (YouTubeRequestBudgetExhaustedException $exception) {
                $outcome = str_contains($exception->getMessage(), 'circuit') ? YouTubeFetchOutcome::SharedCircuitOpen : YouTubeFetchOutcome::RequestBudgetExhausted;

                return new YouTubeProfileResult($outcome, 'request', $requestCount, error: $exception->getMessage());
            } catch (YouTubeTransportException $exception) {
                return new YouTubeProfileResult(YouTubeFetchOutcome::TransportFailure, 'request', $requestCount, durationMilliseconds: $duration, error: $exception->getMessage());
            }

            if ($response->status < 300 || $response->status >= 400) {
                return $response;
            }

            $location = $response->header('location');

            if ($location === null) {
                return $response;
            }

            $target = $this->resolveRedirect($url, $location);
            $targetHost = parse_url($target, PHP_URL_HOST);

            if (is_string($targetHost) && in_array(strtolower($targetHost), self::BLOCKED_HOSTS, true)) {
                $outcome = strtolower($targetHost) === 'consent.youtube.com' ? YouTubeFetchOutcome::ConsentRequired : YouTubeFetchOutcome::SignInRequired;
                $this->budget->block($outcome->value);

                return new YouTubeProfileResult($outcome, 'redirect', $requestCount, $response->status, $response->transport, $duration);
            }

            if (! $this->isSafeYouTubeUrl($target)) {
                return new YouTubeProfileResult(YouTubeFetchOutcome::UnsafeRedirect, 'redirect', $requestCount, $response->status, $response->transport, $duration);
            }

            if (++$redirects > (int) config('youtube.max_redirects')) {
                return new YouTubeProfileResult(YouTubeFetchOutcome::UnsafeRedirect, 'redirect', $requestCount, $response->status, $response->transport, $duration, error: 'Maximum redirects exceeded.');
            }

            $url = $target;
            $query = [];
        }
    }

    private function classifyResponse(YouTubeFetchResult $response, string $stage, int $requestCount, int $duration): ?YouTubeProfileResult
    {
        if (($blocked = $this->blockedOutcome($response)) !== null) {
            $this->budget->block($blocked->value);

            return new YouTubeProfileResult($blocked, $stage, $requestCount, $response->status, $response->transport, $duration);
        }

        if ($response->status >= 200 && $response->status < 300) {
            return null;
        }

        $outcome = match (true) {
            $response->status === 429 => YouTubeFetchOutcome::RateLimited,
            $response->status === 408, $response->status >= 500 => YouTubeFetchOutcome::TransientHttpFailure,
            default => YouTubeFetchOutcome::PermanentHttpFailure,
        };

        return new YouTubeProfileResult($outcome, $stage, $requestCount, $response->status, $response->transport, $duration, $response->retryAfterSeconds);
    }

    private function blockedOutcome(YouTubeFetchResult $response): ?YouTubeFetchOutcome
    {
        $host = parse_url($response->effectiveUri, PHP_URL_HOST);

        if (is_string($host) && strtolower($host) === 'consent.youtube.com') {
            return YouTubeFetchOutcome::ConsentRequired;
        }

        if (is_string($host) && strtolower($host) === 'accounts.google.com') {
            return YouTubeFetchOutcome::SignInRequired;
        }

        $consent = '/<(?:meta|link)\b[^>]*(?:content|href)=["\'][^"\']*https?:\/\/consent\.youtube\.com/i';
        $signIn = '/<(?:meta|link)\b[^>]*(?:content|href)=["\'][^"\']*https?:\/\/accounts\.google\.com/i';

        return match (true) {
            preg_match($consent, $response->body) === 1 => YouTubeFetchOutcome::ConsentRequired,
            preg_match($signIn, $response->body) === 1 => YouTubeFetchOutcome::SignInRequired,
            default => null,
        };
    }

    private function isSafeYouTubeUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || isset($parts['user'], $parts['pass'], $parts['port'])) {
            return false;
        }

        return isset($parts['host']) && in_array(strtolower((string) $parts['host']), ['youtube.com', 'www.youtube.com'], true);
    }

    private function resolveRedirect(string $from, string $location): string
    {
        if (str_starts_with($location, 'https://') || str_starts_with($location, 'http://')) {
            return $location;
        }

        $parts = parse_url($from);

        return ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').'/'.ltrim($location, '/');
    }

    private function extractChannelId(string $html): ?string
    {
        return preg_match('/(?:channel\/|"(?:externalId|channelId)"\s*:\s*")(UC[\w-]{22})/', $html, $matches) === 1 ? $matches[1] : null;
    }

    /** @return list<string>|null */
    private function parseFeedPublishedTimes(string $xml): ?array
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($feed === false || $feed->getName() !== 'feed' || ($feed->getNamespaces(true)[''] ?? null) !== 'http://www.w3.org/2005/Atom') {
            return null;
        }

        $times = [];

        foreach ($feed->entry as $entry) {
            $published = trim((string) $entry->published);

            if ($published === '') {
                return null;
            }

            try {
                Date::parse($published);
            } catch (Throwable) {
                return null;
            }

            $times[] = $published;
        }

        return $times;
    }

    /** @param list<string> $publishedTimes */
    private function countNewItems(array $publishedTimes, NetworkProfile $profile): int
    {
        if ($profile->last_visit_at === null) {
            return count($publishedTimes);
        }

        return count(array_filter($publishedTimes, fn (string $time): bool => Date::parse($time)->greaterThanOrEqualTo($profile->last_visit_at)));
    }
}
