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
use App\Models\NetworkSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

class YouTubeVideoFetchService
{
    private const string CHANNELS_URL = 'https://www.googleapis.com/youtube/v3/channels';

    private const string PLAYLIST_ITEMS_URL = 'https://www.googleapis.com/youtube/v3/playlistItems';

    private const string VIDEOS_URL = 'https://www.googleapis.com/youtube/v3/videos';

    /** @var list<string> */
    private const array CONFIGURATION_REASONS = [
        'keyInvalid',
        'accessNotConfigured',
        'ipRefererBlocked',
        'apiKeyExpired',
    ];

    /** @var list<string> */
    private const array RATE_LIMIT_REASONS = [
        'rateLimitExceeded',
        'userRateLimitExceeded',
        'servingLimitExceeded',
    ];

    /** @var list<string> */
    private const array QUOTA_REASONS = [
        'quotaExceeded',
        'dailyLimitExceeded',
    ];

    public function __construct(
        private readonly YouTubeTransport $transport,
        private readonly YouTubeRequestBudget $budget,
    ) {}

    public function fetch(int $profileId, int $userId, string $runUuid): YouTubeProfileResult
    {
        if (! config('youtube.execution_enabled')) {
            return new YouTubeProfileResult(YouTubeFetchOutcome::Disabled, 'configuration');
        }

        if (! $this->hasValidConfiguration()) {
            return new YouTubeProfileResult(YouTubeFetchOutcome::ConfigurationFailure, 'configuration');
        }

        $profile = NetworkProfile::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->find($profileId);

        if ($profile === null) {
            return new YouTubeProfileResult(YouTubeFetchOutcome::StaleProfile, 'profile');
        }

        $source = NetworkSource::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->find($profile->network_source_id);

        if ($source === null) {
            return new YouTubeProfileResult(YouTubeFetchOutcome::StaleProfile, 'profile');
        }

        $username = $profile->username;
        $sourceId = (int) $source->getKey();
        $sourceUrl = $source->url;
        $snapshotCutoff = $profile->last_visit_at?->toImmutable();
        $requestCount = 0;
        $duration = 0;
        $cachedChannelId = is_string($profile->youtube_channel_id) && trim($profile->youtube_channel_id) !== ''
            ? trim($profile->youtube_channel_id)
            : null;

        if ($cachedChannelId === null) {
            $handle = $this->canonicalHandle($sourceUrl, $username);

            if ($handle === null) {
                return new YouTubeProfileResult(YouTubeFetchOutcome::InvalidUrl, 'channel');
            }

            $channelSelector = ['forHandle' => $handle];
        } else {
            $channelSelector = ['id' => $cachedChannelId];
        }

        $channelResponse = $this->request(
            self::CHANNELS_URL,
            [
                'part' => 'contentDetails',
                'fields' => 'items(id,contentDetails/relatedPlaylists/uploads)',
                ...$channelSelector,
            ],
            'channel',
            $runUuid,
            $requestCount,
            $duration,
        );

        if ($channelResponse instanceof YouTubeProfileResult) {
            return $channelResponse;
        }

        if (($failure = $this->classifyResponse($channelResponse, 'channel', $requestCount, $duration)) !== null) {
            return $failure;
        }

        $channel = $this->parseChannel($channelResponse->body, $cachedChannelId);

        if ($channel instanceof YouTubeFetchOutcome) {
            return $this->failureFromResponse($channel, 'channel', $channelResponse, $requestCount, $duration);
        }

        [$channelId, $uploadsPlaylistId] = $channel;
        $publishedTimes = [];
        $nextPageToken = null;
        $seenPageTokens = [];
        $seenVideoIds = [];
        $lastResponse = $channelResponse;
        $maxPages = (int) config('youtube.max_pages');

        for ($page = 1; $page <= $maxPages; $page++) {
            $query = [
                'part' => 'contentDetails',
                'playlistId' => $uploadsPlaylistId,
                'maxResults' => '50',
                'fields' => 'nextPageToken,items(contentDetails/videoId,contentDetails/videoPublishedAt)',
            ];

            if ($nextPageToken !== null) {
                $query['pageToken'] = $nextPageToken;
            }

            $playlistResponse = $this->request(
                self::PLAYLIST_ITEMS_URL,
                $query,
                'playlist',
                $runUuid,
                $requestCount,
                $duration,
            );

            if ($playlistResponse instanceof YouTubeProfileResult) {
                return $playlistResponse;
            }

            $lastResponse = $playlistResponse;

            if (($failure = $this->classifyResponse($playlistResponse, 'playlist', $requestCount, $duration)) !== null) {
                return $failure;
            }

            $pageData = $this->parsePlaylistPage($playlistResponse->body);

            if ($pageData === null) {
                return $this->failureFromResponse(YouTubeFetchOutcome::MalformedApiResponse, 'playlist', $playlistResponse, $requestCount, $duration);
            }

            foreach ($pageData['videos'] as $video) {
                if (isset($seenVideoIds[$video['id']])) {
                    return $this->failureFromResponse(YouTubeFetchOutcome::MalformedApiResponse, 'playlist', $playlistResponse, $requestCount, $duration);
                }

                $seenVideoIds[$video['id']] = true;
            }

            $pageVideos = array_filter(
                $pageData['videos'],
                fn (array $video): bool => $snapshotCutoff === null || $video['published_at']->greaterThanOrEqualTo($snapshotCutoff),
            );

            if ($pageVideos !== []) {
                $videoIds = array_values(array_map(fn (array $video): string => $video['id'], $pageVideos));
                $videoResponse = $this->request(
                    self::VIDEOS_URL,
                    [
                        'part' => 'contentDetails',
                        'id' => implode(',', $videoIds),
                        'fields' => 'items(id,contentDetails/duration)',
                    ],
                    'video',
                    $runUuid,
                    $requestCount,
                    $duration,
                );

                if ($videoResponse instanceof YouTubeProfileResult) {
                    return $videoResponse;
                }

                $lastResponse = $videoResponse;

                if (($failure = $this->classifyResponse($videoResponse, 'video', $requestCount, $duration)) !== null) {
                    return $failure;
                }

                $longVideoIds = $this->parseVideoDurations($videoResponse->body, $videoIds);

                if ($longVideoIds === null) {
                    return $this->failureFromResponse(YouTubeFetchOutcome::MalformedApiResponse, 'video', $videoResponse, $requestCount, $duration);
                }

                foreach ($pageVideos as $video) {
                    if (isset($longVideoIds[$video['id']])) {
                        $publishedTimes[] = $video['published_at'];
                    }
                }
            }

            if ($snapshotCutoff !== null && $this->containsTimestampBefore(array_map(
                fn (array $video): CarbonImmutable => $video['published_at'],
                $pageData['videos'],
            ), $snapshotCutoff)) {
                $nextPageToken = null;

                break;
            }

            $nextPageToken = $pageData['next_page_token'];

            if ($nextPageToken === null) {
                break;
            }

            if (isset($seenPageTokens[$nextPageToken])) {
                return $this->failureFromResponse(YouTubeFetchOutcome::MalformedApiResponse, 'playlist', $playlistResponse, $requestCount, $duration);
            }

            $seenPageTokens[$nextPageToken] = true;
        }

        if ($nextPageToken !== null) {
            return $this->failureFromResponse(YouTubeFetchOutcome::PaginationLimitExceeded, 'playlist', $lastResponse, $requestCount, $duration);
        }

        $persisted = DB::transaction(function () use (
            $profileId,
            $userId,
            $sourceId,
            $sourceUrl,
            $username,
            $snapshotCutoff,
            $channelId,
            $publishedTimes,
        ): bool {
            $currentSource = NetworkSource::query()->withoutGlobalScopes()->whereKey($sourceId)->lockForUpdate()->first();
            $currentProfile = NetworkProfile::query()->withoutGlobalScopes()->whereKey($profileId)->lockForUpdate()->first();

            if ($currentSource === null
                || $currentSource->trashed()
                || (int) $currentSource->user_id !== $userId
                || $currentSource->url !== $sourceUrl
                || $currentProfile === null
                || $currentProfile->trashed()
                || (int) $currentProfile->user_id !== $userId
                || (int) $currentProfile->network_source_id !== $sourceId
                || $currentProfile->username !== $username
            ) {
                return false;
            }

            $currentCutoff = $currentProfile->last_visit_at?->toImmutable();

            if ($this->cutoffExpanded($snapshotCutoff, $currentCutoff)) {
                return false;
            }

            $currentProfile->forceFill([
                'youtube_channel_id' => $channelId,
                'new_items' => $this->countNewItems($publishedTimes, $currentCutoff),
            ])->save();

            return true;
        }, 3);

        if (! $persisted) {
            return $this->failureFromResponse(YouTubeFetchOutcome::StaleProfile, 'persist', $lastResponse, $requestCount, $duration);
        }

        return new YouTubeProfileResult(
            YouTubeFetchOutcome::Success,
            'complete',
            $requestCount,
            $lastResponse->status,
            $lastResponse->transport,
            $duration,
        );
    }

    private function hasValidConfiguration(): bool
    {
        $apiKey = config('youtube.api_key');

        return is_string($apiKey)
            && trim($apiKey) !== ''
            && (int) config('youtube.max_pages') > 0;
    }

    /** @param array<string, string> $query */
    private function request(
        string $url,
        array $query,
        string $stage,
        string $runUuid,
        int &$requestCount,
        int &$duration,
    ): YouTubeFetchResult|YouTubeProfileResult {
        try {
            $this->budget->reserve($runUuid);
            $requestCount++;
            $response = $this->transport->fetch(new YouTubeFetchRequest($url, $query));
            $duration += $response->durationMilliseconds;

            return $response;
        } catch (YouTubeRequestBudgetExhaustedException $exception) {
            $outcome = str_contains($exception->getMessage(), 'circuit')
                ? YouTubeFetchOutcome::SharedCircuitOpen
                : YouTubeFetchOutcome::RequestBudgetExhausted;

            return new YouTubeProfileResult($outcome, $stage, $requestCount, durationMilliseconds: $duration, error: $exception->getMessage());
        } catch (YouTubeTransportException $exception) {
            return new YouTubeProfileResult(YouTubeFetchOutcome::TransportFailure, $stage, $requestCount, durationMilliseconds: $duration, error: $exception->getMessage());
        }
    }

    private function classifyResponse(YouTubeFetchResult $response, string $stage, int $requestCount, int $duration): ?YouTubeProfileResult
    {
        if ($response->status >= 200 && $response->status < 300) {
            return null;
        }

        $reasons = $this->errorReasons($response->body);
        $outcome = match (true) {
            $response->status === 401,
            array_intersect($reasons, self::CONFIGURATION_REASONS) !== [] => YouTubeFetchOutcome::ConfigurationFailure,
            $response->status === 429 => YouTubeFetchOutcome::RateLimited,
            $response->status === 403 && array_intersect($reasons, self::QUOTA_REASONS) !== [] => YouTubeFetchOutcome::ApiQuotaExhausted,
            $response->status === 403 && array_intersect($reasons, self::RATE_LIMIT_REASONS) !== [] => YouTubeFetchOutcome::RateLimited,
            $stage === 'channel' && in_array('channelNotFound', $reasons, true) => YouTubeFetchOutcome::ChannelIdMissing,
            $stage === 'playlist' && in_array('playlistNotFound', $reasons, true) => YouTubeFetchOutcome::UploadsPlaylistMissing,
            $response->status === 408, $response->status >= 500 => YouTubeFetchOutcome::TransientHttpFailure,
            default => YouTubeFetchOutcome::PermanentHttpFailure,
        };

        if ($outcome === YouTubeFetchOutcome::ApiQuotaExhausted) {
            $this->budget->block($outcome->value);
        }

        return new YouTubeProfileResult(
            $outcome,
            $stage,
            $requestCount,
            $response->status,
            $response->transport,
            $duration,
            $response->retryAfterSeconds,
        );
    }

    /** @return list<string> */
    private function errorReasons(string $body): array
    {
        $json = $this->decodeJsonObject($body);
        $errors = $json['error']['errors'] ?? null;

        if (! is_array($errors) || ! array_is_list($errors)) {
            return [];
        }

        $reasons = [];

        foreach ($errors as $error) {
            if (is_array($error) && isset($error['reason']) && is_string($error['reason'])) {
                $reasons[] = $error['reason'];
            }
        }

        return $reasons;
    }

    /** @return array{string, string}|YouTubeFetchOutcome */
    private function parseChannel(string $body, ?string $expectedChannelId): array|YouTubeFetchOutcome
    {
        $json = $this->decodeJsonObject($body);

        if ($json === null || ! isset($json['items']) || ! is_array($json['items']) || ! array_is_list($json['items'])) {
            return YouTubeFetchOutcome::MalformedApiResponse;
        }

        if ($json['items'] === []) {
            return YouTubeFetchOutcome::ChannelIdMissing;
        }

        if (count($json['items']) !== 1 || ! is_array($json['items'][0])) {
            return YouTubeFetchOutcome::MalformedApiResponse;
        }

        $channelId = $json['items'][0]['id'] ?? null;
        $contentDetails = $json['items'][0]['contentDetails'] ?? null;

        if (! is_string($channelId)
            || preg_match('/^UC[A-Za-z0-9_-]{22}$/D', $channelId) !== 1
            || ($expectedChannelId !== null && $channelId !== $expectedChannelId)
            || ! is_array($contentDetails)
            || ! isset($contentDetails['relatedPlaylists'])
            || ! is_array($contentDetails['relatedPlaylists'])
        ) {
            return YouTubeFetchOutcome::MalformedApiResponse;
        }

        $uploadsPlaylistId = $contentDetails['relatedPlaylists']['uploads'] ?? null;

        if (! is_string($uploadsPlaylistId) || trim($uploadsPlaylistId) === '') {
            return YouTubeFetchOutcome::UploadsPlaylistMissing;
        }

        return [$channelId, $uploadsPlaylistId];
    }

    /** @return array{videos: list<array{id: string, published_at: CarbonImmutable}>, next_page_token: ?string}|null */
    private function parsePlaylistPage(string $body): ?array
    {
        $json = $this->decodeJsonObject($body);

        if ($json === null || ! isset($json['items']) || ! is_array($json['items']) || ! array_is_list($json['items'])) {
            return null;
        }

        $videos = [];

        foreach ($json['items'] as $item) {
            if (! is_array($item) || ! is_array($item['contentDetails'] ?? null)) {
                return null;
            }

            $publishedAt = $item['contentDetails']['videoPublishedAt'] ?? null;
            $videoId = $item['contentDetails']['videoId'] ?? null;

            if (! is_string($videoId)
                || preg_match('/^[A-Za-z0-9_-]{11}$/D', $videoId) !== 1
                || ! is_string($publishedAt)
                || ($timestamp = $this->parseTimestamp($publishedAt)) === null
            ) {
                return null;
            }

            $videos[] = ['id' => $videoId, 'published_at' => $timestamp];
        }

        $nextPageToken = $json['nextPageToken'] ?? null;

        if (array_key_exists('nextPageToken', $json) && (! is_string($nextPageToken) || trim($nextPageToken) === '')) {
            return null;
        }

        return [
            'videos' => $videos,
            'next_page_token' => $nextPageToken,
        ];
    }

    /**
     * @param  list<string>  $expectedIds
     * @return array<string, true>|null
     */
    private function parseVideoDurations(string $body, array $expectedIds): ?array
    {
        $json = $this->decodeJsonObject($body);

        if ($json === null || ! isset($json['items']) || ! is_array($json['items']) || ! array_is_list($json['items'])) {
            return null;
        }

        $expected = array_fill_keys($expectedIds, true);
        $seen = [];
        $longVideoIds = [];

        foreach ($json['items'] as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            $duration = is_array($item) && is_array($item['contentDetails'] ?? null)
                ? ($item['contentDetails']['duration'] ?? null)
                : null;

            if (! is_string($id) || ! isset($expected[$id]) || isset($seen[$id]) || ! is_string($duration)) {
                return null;
            }

            $seconds = $this->durationSeconds($duration);

            if ($seconds === null) {
                return null;
            }

            $seen[$id] = true;

            if ($seconds > 180) {
                $longVideoIds[$id] = true;
            }
        }

        return count($seen) === count($expected) ? $longVideoIds : null;
    }

    private function durationSeconds(string $duration): ?int
    {
        if (preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/D', $duration, $parts) !== 1
            || str_ends_with($duration, 'T')
            || ! preg_match('/\d+[DHMS]/', $duration)
        ) {
            return null;
        }

        return (min(181, (int) ($parts[1] ?? 0)) * 86400)
            + (min(181, (int) ($parts[2] ?? 0)) * 3600)
            + (min(181, (int) ($parts[3] ?? 0)) * 60)
            + min(181, (int) ($parts[4] ?? 0));
    }

    /** @return array<string, mixed>|null */
    private function decodeJsonObject(string $body): ?array
    {
        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($json) ? $json : null;
    }

    private function parseTimestamp(string $value): ?CarbonImmutable
    {
        $pattern = '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})T(?<hour>\d{2}):(?<minute>\d{2}):(?<second>\d{2})(?:\.\d{1,9})?(?<timezone>Z|[+-](?<offset_hour>\d{2}):(?<offset_minute>\d{2}))$/D';

        if (preg_match($pattern, $value, $parts) !== 1
            || ! checkdate((int) $parts['month'], (int) $parts['day'], (int) $parts['year'])
            || (int) $parts['hour'] > 23
            || (int) $parts['minute'] > 59
            || (int) $parts['second'] > 59
        ) {
            return null;
        }

        if ($parts['timezone'] !== 'Z') {
            $offsetHour = (int) $parts['offset_hour'];
            $offsetMinute = (int) $parts['offset_minute'];

            if ($offsetHour > 14 || $offsetMinute > 59 || ($offsetHour === 14 && $offsetMinute !== 0)) {
                return null;
            }
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function canonicalHandle(string $sourceUrl, string $username): ?string
    {
        $parts = parse_url($sourceUrl);

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! isset($parts['host'])
            || ! in_array(strtolower((string) $parts['host']), ['youtube.com', 'www.youtube.com'], true)
            || ($parts['path'] ?? null) !== '/@{username}/videos'
            || isset($parts['user'], $parts['pass'], $parts['port'], $parts['query'], $parts['fragment'])
        ) {
            return null;
        }

        $normalized = str_starts_with($username, '@') ? substr($username, 1) : $username;

        if ($normalized === '' || preg_match('/[\s\/@?#]/u', $normalized) === 1) {
            return null;
        }

        return $normalized;
    }

    /** @param list<CarbonImmutable> $publishedTimes */
    private function containsTimestampBefore(array $publishedTimes, CarbonImmutable $cutoff): bool
    {
        return array_any($publishedTimes, fn ($publishedAt) => $publishedAt->lessThan($cutoff));
    }

    private function cutoffExpanded(?CarbonImmutable $snapshot, ?CarbonImmutable $current): bool
    {
        if ($snapshot === null) {
            return false;
        }

        return $current === null || $current->lessThan($snapshot);
    }

    /** @param list<CarbonImmutable> $publishedTimes */
    private function countNewItems(array $publishedTimes, ?CarbonImmutable $cutoff): int
    {
        if ($cutoff === null) {
            return count($publishedTimes);
        }

        return count(array_filter(
            $publishedTimes,
            fn (CarbonImmutable $publishedAt): bool => $publishedAt->greaterThanOrEqualTo($cutoff),
        ));
    }

    private function failureFromResponse(
        YouTubeFetchOutcome $outcome,
        string $stage,
        YouTubeFetchResult $response,
        int $requestCount,
        int $duration,
    ): YouTubeProfileResult {
        return new YouTubeProfileResult(
            $outcome,
            $stage,
            $requestCount,
            $response->status,
            $response->transport,
            $duration,
            $response->retryAfterSeconds,
        );
    }
}
