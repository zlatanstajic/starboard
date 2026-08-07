<?php

declare(strict_types=1);

namespace Tests\Feature\Services\YouTube;

use App\Contracts\YouTube\YouTubeTransport;
use App\DataTransferObjects\YouTube\YouTubeFetchRequest;
use App\DataTransferObjects\YouTube\YouTubeFetchResult;
use App\Enums\YouTubeFetchOutcome;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use App\Models\YouTubeFetchDailyBudget;
use App\Models\YouTubeFetchRun;
use App\Services\YouTube\YouTubeRequestBudget;
use App\Services\YouTube\YouTubeVideoFetchService;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YouTubeVideoFetchServiceTest extends TestCase
{
    private const string CHANNEL_ID = 'UC_x5XG1OV2P6uZZ5FSM9Ttw';

    private const string UPLOADS_PLAYLIST_ID = 'UU_x5XG1OV2P6uZZ5FSM9Ttw';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Date::setTestNow('2026-08-07 12:00:00 UTC');
        config()->set('youtube.execution_enabled', true);
        config()->set('youtube.api_key', 'server-only-test-key');
        config()->set('youtube.max_pages', 10);
        config()->set('youtube.daily_request_limit', 500);
        YouTubeFetchDailyBudget::query()->delete();
    }

    /** @return array<string, array{int, string, string, YouTubeFetchOutcome}> */
    public static function errorOutcomeProvider(): array
    {
        return [
            'unauthorized' => [401, 'unknown', 'channel', YouTubeFetchOutcome::ConfigurationFailure],
            'invalid key' => [400, 'keyInvalid', 'channel', YouTubeFetchOutcome::ConfigurationFailure],
            'API disabled' => [403, 'accessNotConfigured', 'channel', YouTubeFetchOutcome::ConfigurationFailure],
            'IP or referrer blocked' => [403, 'ipRefererBlocked', 'channel', YouTubeFetchOutcome::ConfigurationFailure],
            'expired key' => [403, 'apiKeyExpired', 'channel', YouTubeFetchOutcome::ConfigurationFailure],
            'HTTP rate limit' => [429, 'quotaExceeded', 'channel', YouTubeFetchOutcome::RateLimited],
            'project rate limit' => [403, 'rateLimitExceeded', 'channel', YouTubeFetchOutcome::RateLimited],
            'user rate limit' => [403, 'userRateLimitExceeded', 'channel', YouTubeFetchOutcome::RateLimited],
            'serving rate limit' => [403, 'servingLimitExceeded', 'channel', YouTubeFetchOutcome::RateLimited],
            'quota' => [403, 'quotaExceeded', 'channel', YouTubeFetchOutcome::ApiQuotaExhausted],
            'daily quota' => [403, 'dailyLimitExceeded', 'channel', YouTubeFetchOutcome::ApiQuotaExhausted],
            'channel missing' => [404, 'channelNotFound', 'channel', YouTubeFetchOutcome::ChannelIdMissing],
            'playlist missing' => [404, 'playlistNotFound', 'playlist', YouTubeFetchOutcome::UploadsPlaylistMissing],
            'request timeout' => [408, 'unknown', 'channel', YouTubeFetchOutcome::TransientHttpFailure],
            'provider unavailable' => [503, 'unknown', 'channel', YouTubeFetchOutcome::TransientHttpFailure],
            'other client failure' => [400, 'invalidParameter', 'channel', YouTubeFetchOutcome::PermanentHttpFailure],
        ];
    }

    public function test_missing_api_key_fails_before_reserving_or_sending_a_request(): void
    {
        config()->set('youtube.api_key', '   ');
        [$profile, $run] = $this->profileAndRun(newItems: 7);
        $transport = new ScriptedYouTubeTransport([]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::ConfigurationFailure, $result->outcome);
        $this->assertSame([], $transport->requests);
        $this->assertSame(0, $run->fresh()->request_count);
        $this->assertSame(7, $profile->fresh()->new_items);
        $this->assertSame(0, YouTubeFetchDailyBudget::query()->count());
    }

    public function test_uncached_handle_uses_exact_api_queries_and_persists_atomically(): void
    {
        [$profile, $run] = $this->profileAndRun(cached: false, username: '@channel');
        $transport = new ScriptedYouTubeTransport([
            $this->channelResponse(),
            $this->playlistResponse([now()->subHour()->toISOString()]),
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::Success, $result->outcome);
        $this->assertSame([
            'part' => 'contentDetails',
            'fields' => 'items(id,contentDetails/relatedPlaylists/uploads)',
            'forHandle' => 'channel',
        ], $transport->requests[0]->query);
        $this->assertSame('https://www.googleapis.com/youtube/v3/channels', $transport->requests[0]->url);
        $this->assertSame([
            'part' => 'contentDetails',
            'playlistId' => self::UPLOADS_PLAYLIST_ID,
            'maxResults' => '50',
            'fields' => 'nextPageToken,items(contentDetails/videoPublishedAt)',
        ], $transport->requests[1]->query);
        $this->assertSame('https://www.googleapis.com/youtube/v3/playlistItems', $transport->requests[1]->url);
        $this->assertSame(self::CHANNEL_ID, $profile->fresh()->youtube_channel_id);
        $this->assertSame(1, $profile->fresh()->new_items);
        $this->assertSame(2, $result->requestCount);
        $this->assertSame(2, $run->fresh()->request_count);
        $this->assertSame(2, YouTubeFetchDailyBudget::query()->firstOrFail()->reserved_requests);
    }

    public function test_cached_channel_id_uses_id_selector_even_with_a_noncanonical_source(): void
    {
        [$profile, $run] = $this->profileAndRun(sourceUrl: 'http://internal.invalid/{username}');
        $transport = new ScriptedYouTubeTransport([
            $this->channelResponse(),
            $this->playlistResponse([]),
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::Success, $result->outcome);
        $this->assertSame(self::CHANNEL_ID, $transport->requests[0]->query['id']);
        $this->assertArrayNotHasKey('forHandle', $transport->requests[0]->query);
    }

    public function test_uncached_profile_rejects_noncanonical_handle_source_without_a_request(): void
    {
        [$profile, $run] = $this->profileAndRun(cached: false, sourceUrl: 'http://youtube.com/@{username}/videos');
        $transport = new ScriptedYouTubeTransport([]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::InvalidUrl, $result->outcome);
        $this->assertSame([], $transport->requests);
    }

    public function test_multi_page_fetch_counts_inclusive_cutoff_and_stops_on_older_item(): void
    {
        $cutoff = now()->subDay();
        [$profile, $run] = $this->profileAndRun(lastVisitAt: $cutoff);
        $transport = new ScriptedYouTubeTransport([
            $this->channelResponse(),
            $this->playlistResponse([
                now()->subHour()->toISOString(),
                now()->subHours(3)->toISOString(),
            ], 'page-2'),
            $this->playlistResponse([
                $cutoff->toISOString(),
                $cutoff->copy()->subSecond()->toISOString(),
            ], 'unused-page'),
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::Success, $result->outcome);
        $this->assertSame(3, $profile->fresh()->new_items);
        $this->assertCount(3, $transport->requests);
        $this->assertSame('page-2', $transport->requests[2]->query['pageToken']);
    }

    public function test_pagination_cap_preserves_uncached_channel_id_and_previous_count(): void
    {
        config()->set('youtube.max_pages', 2);
        [$profile, $run] = $this->profileAndRun(cached: false, lastVisitAt: now()->subYears(10), newItems: 9);
        $transport = new ScriptedYouTubeTransport([
            $this->channelResponse(),
            $this->playlistResponse([now()->subHour()->toISOString()], 'page-2'),
            $this->playlistResponse([now()->subDay()->toISOString()], 'page-3'),
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::PaginationLimitExceeded, $result->outcome);
        $this->assertNull($profile->fresh()->youtube_channel_id);
        $this->assertSame(9, $profile->fresh()->new_items);
        $this->assertCount(3, $transport->requests);
    }

    public function test_repeated_page_token_is_rejected_without_partial_persistence(): void
    {
        [$profile, $run] = $this->profileAndRun(newItems: 6);
        $transport = new ScriptedYouTubeTransport([
            $this->channelResponse(),
            $this->playlistResponse([], 'repeat'),
            $this->playlistResponse([], 'repeat'),
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::MalformedApiResponse, $result->outcome);
        $this->assertSame(6, $profile->fresh()->new_items);
    }

    public function test_empty_channel_items_are_reported_as_missing(): void
    {
        [$profile, $run] = $this->profileAndRun(newItems: 3);
        $transport = new ScriptedYouTubeTransport([$this->response(200, ['items' => []])]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::ChannelIdMissing, $result->outcome);
        $this->assertSame(3, $profile->fresh()->new_items);
    }

    public function test_missing_uploads_playlist_is_reported_separately(): void
    {
        [$profile, $run] = $this->profileAndRun(newItems: 3);
        $transport = new ScriptedYouTubeTransport([$this->response(200, [
            'items' => [[
                'id' => self::CHANNEL_ID,
                'contentDetails' => ['relatedPlaylists' => []],
            ]],
        ])]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::UploadsPlaylistMissing, $result->outcome);
    }

    public function test_malformed_json_schema_and_timestamp_preserve_previous_values(): void
    {
        foreach ([
            '{not-json',
            json_encode(['items' => [['contentDetails' => ['videoPublishedAt' => 'tomorrow']]]], JSON_THROW_ON_ERROR),
            json_encode(['items' => [['contentDetails' => ['videoPublishedAt' => '2026-02-30T12:00:00Z']]]], JSON_THROW_ON_ERROR),
        ] as $body) {
            [$profile, $run] = $this->profileAndRun(newItems: 8);
            $transport = new ScriptedYouTubeTransport([
                $this->channelResponse(),
                $this->rawResponse(200, $body),
            ]);

            $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

            $this->assertSame(YouTubeFetchOutcome::MalformedApiResponse, $result->outcome);
            $this->assertSame(8, $profile->fresh()->new_items);
        }
    }

    public function test_empty_playlist_is_a_successful_zero(): void
    {
        [$profile, $run] = $this->profileAndRun(newItems: 8);
        $transport = new ScriptedYouTubeTransport([$this->channelResponse(), $this->playlistResponse([])]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::Success, $result->outcome);
        $this->assertSame(0, $profile->fresh()->new_items);
    }

    #[DataProvider('errorOutcomeProvider')]
    public function test_api_errors_are_mapped_by_status_reason_and_stage(
        int $status,
        string $reason,
        string $stage,
        YouTubeFetchOutcome $expected,
    ): void {
        [$profile, $run] = $this->profileAndRun(newItems: 5);
        $error = $this->errorResponse($status, $reason);
        $script = $stage === 'playlist' ? [$this->channelResponse(), $error] : [$error];

        $result = $this->service(new ScriptedYouTubeTransport($script))->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame($expected, $result->outcome);
        $this->assertSame(in_array($expected, [YouTubeFetchOutcome::RateLimited, YouTubeFetchOutcome::TransientHttpFailure], true), $result->outcome->retryable());
        $this->assertNull($result->error, 'Provider messages and reasons must not be persisted in the result error.');
        $this->assertSame(5, $profile->fresh()->new_items);

        if ($expected === YouTubeFetchOutcome::ApiQuotaExhausted) {
            $budget = YouTubeFetchDailyBudget::query()->firstOrFail();
            $this->assertTrue($budget->blocked_until->isFuture());
            $this->assertSame(YouTubeFetchOutcome::ApiQuotaExhausted->value, $budget->block_reason);
        }
    }

    public function test_failure_on_a_later_page_does_not_persist_channel_or_count(): void
    {
        [$profile, $run] = $this->profileAndRun(cached: false, newItems: 11);
        $transport = new ScriptedYouTubeTransport([
            $this->channelResponse(),
            $this->playlistResponse([now()->subHour()->toISOString()], 'page-2'),
            $this->errorResponse(503, 'backendError'),
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::TransientHttpFailure, $result->outcome);
        $this->assertNull($profile->fresh()->youtube_channel_id);
        $this->assertSame(11, $profile->fresh()->new_items);
    }

    public function test_error_classification_checks_every_provider_reason(): void
    {
        [$profile, $run] = $this->profileAndRun(newItems: 5);
        $response = $this->response(403, [
            'error' => [
                'errors' => [
                    ['reason' => 'unknown'],
                    ['reason' => 'quotaExceeded'],
                ],
            ],
        ]);

        $result = $this->service(new ScriptedYouTubeTransport([$response]))
            ->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::ApiQuotaExhausted, $result->outcome);
        $this->assertSame(5, $profile->fresh()->new_items);
    }

    public function test_visit_race_with_a_later_cutoff_recomputes_using_the_locked_value(): void
    {
        [$profile, $run] = $this->profileAndRun(lastVisitAt: now()->subDay(), newItems: 4);
        $transport = new ScriptedYouTubeTransport([
            $this->channelResponse(),
            function () use ($profile): YouTubeFetchResult {
                NetworkProfile::query()->withoutGlobalScopes()->whereKey($profile->id)->update(['last_visit_at' => now()]);

                return $this->playlistResponse([now()->subHour()->toISOString()]);
            },
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::Success, $result->outcome);
        $this->assertSame(0, $profile->fresh()->new_items);
    }

    public function test_visit_race_that_expands_the_window_discards_the_result(): void
    {
        [$profile, $run] = $this->profileAndRun(lastVisitAt: now()->subDay(), newItems: 4);
        $transport = new ScriptedYouTubeTransport([
            $this->channelResponse(),
            function () use ($profile): YouTubeFetchResult {
                NetworkProfile::query()->withoutGlobalScopes()->whereKey($profile->id)->update(['last_visit_at' => now()->subDays(2)]);

                return $this->playlistResponse([now()->subHour()->toISOString()]);
            },
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::StaleProfile, $result->outcome);
        $this->assertSame(4, $profile->fresh()->new_items);
    }

    public function test_profile_or_source_edit_races_discard_the_result(): void
    {
        foreach (['profile', 'source'] as $race) {
            [$profile, $run, $source] = $this->profileAndRun(newItems: 4);
            $transport = new ScriptedYouTubeTransport([
                $this->channelResponse(),
                function () use ($profile, $source, $race): YouTubeFetchResult {
                    if ($race === 'profile') {
                        NetworkProfile::query()->withoutGlobalScopes()->whereKey($profile->id)->update(['username' => 'edited-during-fetch']);
                    } else {
                        NetworkSource::query()->withoutGlobalScopes()->whereKey($source->id)->update(['url' => 'https://youtube.com/@other/videos']);
                    }

                    return $this->playlistResponse([now()->subHour()->toISOString()]);
                },
            ]);

            $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

            $this->assertSame(YouTubeFetchOutcome::StaleProfile, $result->outcome);
            $this->assertSame(4, $profile->fresh()->new_items);
        }
    }

    public function test_budget_is_reserved_before_each_physical_call(): void
    {
        config()->set('youtube.daily_request_limit', 2);
        [$profile, $run] = $this->profileAndRun(newItems: 4);
        $transport = new ScriptedYouTubeTransport([
            $this->channelResponse(),
            $this->playlistResponse([], 'page-2'),
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::RequestBudgetExhausted, $result->outcome);
        $this->assertCount(2, $transport->requests);
        $this->assertSame(2, $result->requestCount);
        $this->assertSame(2, $run->fresh()->request_count);
        $this->assertSame(4, $profile->fresh()->new_items);
    }

    private function service(YouTubeTransport $transport): YouTubeVideoFetchService
    {
        return new YouTubeVideoFetchService($transport, new YouTubeRequestBudget);
    }

    /** @return array{NetworkProfile, YouTubeFetchRun, NetworkSource} */
    private function profileAndRun(
        ?Carbon $lastVisitAt = null,
        int $newItems = 0,
        bool $cached = true,
        string $sourceUrl = 'https://youtube.com/@{username}/videos',
        string $username = 'channel',
    ): array {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create([
            'user_id' => $user->getKey(),
            'url' => $sourceUrl,
        ]);
        $profile = NetworkProfile::factory()->create([
            'user_id' => $user->getKey(),
            'network_source_id' => $source->getKey(),
            'username' => $username,
            'youtube_channel_id' => $cached ? self::CHANNEL_ID : null,
            'last_visit_at' => $lastVisitAt ?? now()->subDay(),
            'new_items' => $newItems,
        ]);
        $run = YouTubeFetchRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'network_profile_id' => $profile->id,
            'user_id' => $user->getKey(),
            'stage' => 'test',
        ]);

        return [$profile, $run, $source];
    }

    private function channelResponse(): YouTubeFetchResult
    {
        return $this->response(200, [
            'items' => [[
                'id' => self::CHANNEL_ID,
                'contentDetails' => [
                    'relatedPlaylists' => ['uploads' => self::UPLOADS_PLAYLIST_ID],
                ],
            ]],
        ]);
    }

    /** @param list<string> $timestamps */
    private function playlistResponse(array $timestamps, ?string $nextPageToken = null): YouTubeFetchResult
    {
        $body = [
            'items' => array_map(
                fn (string $timestamp): array => ['contentDetails' => ['videoPublishedAt' => $timestamp]],
                $timestamps,
            ),
        ];

        if ($nextPageToken !== null) {
            $body['nextPageToken'] = $nextPageToken;
        }

        return $this->response(200, $body);
    }

    private function errorResponse(int $status, string $reason): YouTubeFetchResult
    {
        return $this->response($status, [
            'error' => [
                'message' => 'Provider text must never be stored',
                'errors' => [[
                    'reason' => $reason,
                    'message' => 'Provider text must never be stored',
                ]],
            ],
        ]);
    }

    /** @param array<string, mixed> $body */
    private function response(int $status, array $body): YouTubeFetchResult
    {
        return $this->rawResponse($status, json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function rawResponse(int $status, string $body): YouTubeFetchResult
    {
        return new YouTubeFetchResult($status, $body, [], 'https://www.googleapis.com/youtube/v3/test', 'scripted', 1, 1, null);
    }
}

final class ScriptedYouTubeTransport implements YouTubeTransport
{
    /** @var list<YouTubeFetchRequest> */
    public array $requests = [];

    /** @param list<YouTubeFetchResult|Closure(): YouTubeFetchResult> $script */
    public function __construct(private array $script) {}

    public function fetch(YouTubeFetchRequest $request): YouTubeFetchResult
    {
        $this->requests[] = $request;
        $step = array_shift($this->script);

        if ($step instanceof Closure) {
            return $step();
        }

        throw_unless($step instanceof YouTubeFetchResult, LogicException::class, 'The scripted transport was exhausted.');

        return $step;
    }
}
