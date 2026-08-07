<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\YouTubeFetchOutcome;
use App\Jobs\FetchYouTubeNewItemsJob;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use App\Models\YouTubeFetchDailyBudget;
use App\Models\YouTubeFetchRun;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Override;
use Tests\TestCase;

class FetchYouTubeNewItemsJobTest extends TestCase
{
    private const string CHANNEL_ID = 'UC_x5XG1OV2P6uZZ5FSM9Ttw';

    private const string UPLOADS_PLAYLIST_ID = 'UU_x5XG1OV2P6uZZ5FSM9Ttw';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Date::setTestNow('2026-08-07 12:00:00 UTC');
        config()->set('youtube.execution_enabled', true);
        config()->set('youtube.api_key', 'job-test-api-key');
        config()->set('youtube.max_pages', 10);
        YouTubeFetchDailyBudget::query()->delete();
    }

    public function test_counts_videos_published_at_or_after_last_visit_via_data_api(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response($this->channelPayload()),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response($this->playlistPayload([
                now()->subHours(2),
                now()->subDays(3),
                now()->subWeeks(2),
            ])),
        ]);
        $profile = $this->youtubeProfile(now()->subDays(7));
        $job = new FetchYouTubeNewItemsJob($profile);

        $job->handle();

        $this->assertSame(2, $profile->fresh()->new_items);
        $this->assertSame(self::CHANNEL_ID, $profile->fresh()->youtube_channel_id);
        $this->assertSame(YouTubeFetchOutcome::Success->value, YouTubeFetchRun::query()->where('uuid', $job->runUuid)->value('outcome'));
        Http::assertSentCount(2);
    }

    public function test_uncached_job_resolves_handle_and_sends_server_key_in_header_only(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response($this->channelPayload()),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response($this->playlistPayload([])),
        ]);
        $profile = $this->youtubeProfile(now()->subDay(), username: '@channel');

        (new FetchYouTubeNewItemsJob($profile))->handle();

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/channels')) {
                return true;
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['forHandle'] ?? null) === 'channel'
                && ! array_key_exists('key', $query)
                && ! str_contains($request->url(), 'job-test-api-key')
                && $request->header('X-Goog-Api-Key') === ['job-test-api-key'];
        });
    }

    public function test_cached_channel_id_still_resolves_the_uploads_playlist_by_id(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response($this->channelPayload()),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response($this->playlistPayload([now()->subHour()])),
        ]);
        $profile = $this->youtubeProfile(now()->subDay(), cached: true);

        (new FetchYouTubeNewItemsJob($profile))->handle();

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/channels')) {
                return true;
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['id'] ?? null) === self::CHANNEL_ID && ! array_key_exists('forHandle', $query);
        });
        $this->assertSame(1, $profile->fresh()->new_items);
    }

    public function test_job_follows_playlist_pages(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/channels')) {
                return Http::response($this->channelPayload());
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['pageToken'] ?? null) === 'second'
                ? Http::response($this->playlistPayload([now()->subHours(2)]))
                : Http::response($this->playlistPayload([now()->subHour()], 'second'));
        });
        $profile = $this->youtubeProfile(now()->subDay(), cached: true);

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(2, $profile->fresh()->new_items);
        Http::assertSentCount(3);
    }

    public function test_job_writes_new_items_when_run_through_sync_queue(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response($this->channelPayload()),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response($this->playlistPayload([
                now()->subHours(2),
                now()->subDays(3),
            ])),
        ]);
        $profile = $this->youtubeProfile(now()->subDays(7));

        dispatch(new FetchYouTubeNewItemsJob($profile));

        $this->assertSame(2, $profile->fresh()->new_items);
    }

    public function test_api_failure_preserves_the_previous_count_and_uncached_channel_id(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response($this->channelPayload()),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
                'error' => [
                    'message' => 'backend details',
                    'errors' => [['reason' => 'backendError', 'message' => 'backend details']],
                ],
            ], 503),
        ]);
        $profile = $this->youtubeProfile(now()->subDay());
        $profile->forceFill(['new_items' => 5])->save();
        $job = new FetchYouTubeNewItemsJob($profile);

        $job->handle();

        $this->assertSame(5, $profile->fresh()->new_items);
        $this->assertNull($profile->fresh()->youtube_channel_id);
        $run = YouTubeFetchRun::query()->where('uuid', $job->runUuid)->firstOrFail();
        $this->assertSame(YouTubeFetchOutcome::TransientHttpFailure->value, $run->outcome);
        $this->assertNull($run->error);
    }

    public function test_malformed_api_response_preserves_previous_values(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response($this->channelPayload()),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
                'items' => [['contentDetails' => ['videoPublishedAt' => 'not-a-timestamp']]],
            ]),
        ]);
        $profile = $this->youtubeProfile(now()->subDay(), cached: true);
        $profile->forceFill(['new_items' => 3])->save();

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(3, $profile->fresh()->new_items);
    }

    public function test_missing_key_makes_no_http_request_and_records_configuration_failure(): void
    {
        config()->set('youtube.api_key', '');
        Http::preventStrayRequests();
        Http::fake();
        $profile = $this->youtubeProfile(now()->subDay());
        $job = new FetchYouTubeNewItemsJob($profile);

        $job->handle();

        Http::assertNothingSent();
        $this->assertSame(YouTubeFetchOutcome::ConfigurationFailure->value, YouTubeFetchRun::query()->where('uuid', $job->runUuid)->value('outcome'));
    }

    public function test_noncanonical_uncached_source_is_not_requested(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $profile = $this->youtubeProfile(now()->subDay(), sourceUrl: 'http://169.254.169.254/@{username}/videos');

        (new FetchYouTubeNewItemsJob($profile))->handle();

        Http::assertNothingSent();
        $this->assertSame(0, $profile->fresh()->new_items);
    }

    private function youtubeProfile(
        Carbon $lastVisitAt,
        bool $cached = false,
        string $username = 'channel',
        string $sourceUrl = 'https://youtube.com/@{username}/videos',
    ): NetworkProfile {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create([
            'user_id' => $user->getKey(),
            'url' => $sourceUrl,
        ]);

        return NetworkProfile::factory()->create([
            'user_id' => $user->getKey(),
            'network_source_id' => $source->getKey(),
            'username' => $username,
            'youtube_channel_id' => $cached ? self::CHANNEL_ID : null,
            'last_visit_at' => $lastVisitAt,
            'new_items' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function channelPayload(): array
    {
        return [
            'items' => [[
                'id' => self::CHANNEL_ID,
                'contentDetails' => [
                    'relatedPlaylists' => ['uploads' => self::UPLOADS_PLAYLIST_ID],
                ],
            ]],
        ];
    }

    /**
     * @param  list<Carbon>  $publishedAts
     * @return array<string, mixed>
     */
    private function playlistPayload(array $publishedAts, ?string $nextPageToken = null): array
    {
        $payload = [
            'items' => array_map(
                fn (Carbon $publishedAt): array => [
                    'contentDetails' => ['videoPublishedAt' => $publishedAt->toISOString()],
                ],
                $publishedAts,
            ),
        ];

        if ($nextPageToken !== null) {
            $payload['nextPageToken'] = $nextPageToken;
        }

        return $payload;
    }
}
