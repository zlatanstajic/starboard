<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NetworkProfile;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchYouTubeNewItemsJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * A realistic browser User-Agent so YouTube returns the full
     * server-rendered HTML shell (including the canonical channel link)
     * rather than a consent wall or a 429.
     */
    private const string USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * YouTube's per-channel Atom feed. Fed the resolved channel id, it returns
     * the most recent uploads with absolute <published> timestamps, avoiding the
     * fragile HTML/ytInitialData scraping the job previously relied on.
     */
    private const string FEED_URL = 'https://www.youtube.com/feeds/videos.xml';

    /**
     * Hosts YouTube redirects to (or references in the served page) when it
     * detects bot-like traffic and serves a sign-in/consent wall instead of
     * the channel page.
     *
     * @var list<string>
     */
    private const array BLOCKED_HOSTS = [
        'accounts.google.com',
        'consent.youtube.com',
    ];

    /**
     * The number of seconds the job may run before timing out. Large enough to
     * cover the rare first run that fetches the channel page and the feed back
     * to back before the channel id is cached.
     */
    public int $timeout = 45;

    /**
     * Retains cookies set while YouTube redirects through its regional consent
     * flow before returning to the requested channel page.
     */
    private readonly CookieJar $cookies;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly NetworkProfile $networkProfile
    ) {
        $this->cookies = new CookieJar;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $channelId = $this->resolveChannelId();

        if ($channelId === null) {
            return;
        }

        $publishedTimes = $this->fetchPublishedTimes($channelId);

        if ($publishedTimes === null) {
            return;
        }

        $count = $this->countNewItems($publishedTimes);

        if ($count !== $this->networkProfile->new_items) {
            $this->networkProfile->update(['new_items' => $count]);
        }
    }

    /**
     * Return the profile's YouTube channel id, resolving it from the channel
     * page and caching it on the profile the first time. Returns null (failing
     * soft) when the URL is invalid, the request is blocked/fails, or the
     * channel id cannot be found.
     */
    private function resolveChannelId(): ?string
    {
        $cached = $this->networkProfile->youtube_channel_id;

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $url = $this->networkProfile->profileUrl();

        if (! $this->isValidYouTubeUrl($url)) {
            Log::warning('FetchYouTubeNewItemsJob skipped invalid URL.', [
                'network_profile_id' => $this->networkProfile->id,
                'url' => $url,
            ]);

            return null;
        }

        $response = $this->request($url, ['hl' => 'en']);

        if ($response === null || ! $response->ok()) {
            Log::warning('FetchYouTubeNewItemsJob received non-OK channel response.', [
                'network_profile_id' => $this->networkProfile->id,
                'url' => $url,
                'status' => $response?->status(),
            ]);

            return null;
        }

        if ($this->isBlockedResponse($response)) {
            Log::warning('FetchYouTubeNewItemsJob request blocked by YouTube.', [
                'network_profile_id' => $this->networkProfile->id,
                'url' => $url,
                'effective_host' => $response->effectiveUri()?->getHost(),
            ]);

            return null;
        }

        $channelId = $this->extractChannelId($response->body());

        if ($channelId === null) {
            Log::warning('FetchYouTubeNewItemsJob could not resolve channel id.', [
                'network_profile_id' => $this->networkProfile->id,
                'url' => $url,
            ]);

            return null;
        }

        $this->networkProfile->update(['youtube_channel_id' => $channelId]);

        return $channelId;
    }

    /**
     * Fetch the channel's Atom feed and return each video's absolute published
     * timestamp. Returns null (failing soft) when the request fails, is non-OK,
     * or the feed cannot be parsed.
     *
     * @return list<string>|null
     */
    private function fetchPublishedTimes(string $channelId): ?array
    {
        $response = $this->request(self::FEED_URL, ['channel_id' => $channelId]);

        if ($response === null || ! $response->ok()) {
            Log::warning('FetchYouTubeNewItemsJob received non-OK feed response.', [
                'network_profile_id' => $this->networkProfile->id,
                'channel_id' => $channelId,
                'status' => $response?->status(),
            ]);

            return null;
        }

        $publishedTimes = $this->parseFeedPublishedTimes($response->body());

        if ($publishedTimes === null) {
            Log::warning('FetchYouTubeNewItemsJob could not parse feed.', [
                'network_profile_id' => $this->networkProfile->id,
                'channel_id' => $channelId,
            ]);
        }

        return $publishedTimes;
    }

    /**
     * Perform a GET request with the browser-like headers YouTube expects,
     * returning null when the request throws.
     *
     * @param  array<string, string>  $query
     */
    private function request(string $url, array $query = []): ?Response
    {
        try {
            return Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept-Language' => 'en',
            ])->withOptions([
                'cookies' => $this->cookies,
            ])->timeout(15)->get($url, $query);
        } catch (Throwable $e) {
            Log::warning('FetchYouTubeNewItemsJob request failed.', [
                'network_profile_id' => $this->networkProfile->id,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Ensure the resolved URL is an https YouTube URL before fetching it,
     * guarding against SSRF via crafted usernames/source URLs.
     */
    private function isValidYouTubeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if ($scheme !== 'https' || ! is_string($host)) {
            return false;
        }

        $host = strtolower($host);

        return $host === 'youtube.com' || $host === 'www.youtube.com';
    }

    /**
     * Whether the response indicates YouTube blocked the request with a
     * sign-in/consent wall rather than serving the channel page: either the
     * request was redirected to a known blocked host, or the served page is
     * itself a wall that client-side redirects/canonicalises to one.
     */
    private function isBlockedResponse(Response $response): bool
    {
        $effectiveHost = $response->effectiveUri()?->getHost();

        if ($effectiveHost !== null && $this->isBlockedHost($effectiveHost)) {
            return true;
        }

        return $this->bodyRedirectsToBlockedHost($response->body());
    }

    /**
     * Whether the served page is a consent/sign-in wall that points a refresh
     * meta tag or canonical link at a blocked host. This deliberately ignores
     * the incidental accounts.google.com sign-in links present on every normal
     * channel page, which must not count as a block.
     */
    private function bodyRedirectsToBlockedHost(string $body): bool
    {
        $hosts = implode('|', array_map(preg_quote(...), self::BLOCKED_HOSTS));

        $metaRefresh = '/http-equiv=["\']?refresh["\'][^>]*content=["\'][^"\']*https?:\/\/(?:[\w-]+\.)*(?:'.$hosts.')/i';
        $canonical = '/rel=["\']?canonical["\'][^>]*href=["\']https?:\/\/(?:[\w-]+\.)*(?:'.$hosts.')/i';

        return preg_match($metaRefresh, $body) === 1
            || preg_match($canonical, $body) === 1;
    }

    /**
     * Whether the given host matches one of the known blocked-signal hosts.
     */
    private function isBlockedHost(string $host): bool
    {
        return in_array(strtolower($host), self::BLOCKED_HOSTS, true);
    }

    /**
     * Extract the channel id (a "UC"-prefixed 24-character token) from the
     * channel page. The canonical channel link and the externalId/channelId
     * fields are stable across YouTube's HTML experiments, unlike ytInitialData.
     */
    private function extractChannelId(string $html): ?string
    {
        if (preg_match('/(?:channel\/|"(?:externalId|channelId)"\s*:\s*")(UC[\w-]{22})/', $html, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract each video's absolute published timestamp from the Atom feed.
     * Returns null when the XML cannot be parsed so the caller can fail soft.
     *
     * @return list<string>|null
     */
    private function parseFeedPublishedTimes(string $xml): ?array
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($feed === false) {
            return null;
        }

        $times = [];

        foreach ($feed->entry as $entry) {
            $published = trim((string) $entry->published);

            if ($published !== '') {
                $times[] = $published;
            }
        }

        return $times;
    }

    /**
     * Count how many videos were published at/after the profile's last visit.
     *
     * @param  list<string>  $publishedTimes
     */
    private function countNewItems(array $publishedTimes): int
    {
        $lastVisitAt = $this->networkProfile->last_visit_at;

        if ($lastVisitAt === null) {
            return count($publishedTimes);
        }

        $count = 0;

        foreach ($publishedTimes as $time) {
            try {
                $publishedAt = Date::parse($time);
            } catch (Throwable) {
                continue;
            }

            if ($publishedAt->greaterThanOrEqualTo($lastVisitAt)) {
                $count++;
            }
        }

        return $count;
    }
}
