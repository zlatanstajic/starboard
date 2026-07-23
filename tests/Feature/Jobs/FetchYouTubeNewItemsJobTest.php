<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\FetchYouTubeNewItemsJob;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchYouTubeNewItemsJobTest extends TestCase
{
    /**
     * The channel id embedded in the channel-page fixture's canonical link.
     */
    private const string CHANNEL_ID = 'UC_x5XG1OV2P6uZZ5FSM9Ttw';

    public function test_counts_videos_published_at_or_after_last_visit(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'youtube.com/feeds/videos.xml*' => Http::response($this->feed([
                now()->subHours(2),
                now()->subDays(3),
                now()->subWeeks(2),
            ]), 200),
            'youtube.com/*' => Http::response($this->channelPage(), 200),
        ]);

        // Feed entries: 2 hours, 3 days, 2 weeks ago. With last visit 7 days ago
        // only the two-hour and three-day uploads count.
        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(2, $profile->fresh()->new_items);
    }

    public function test_counts_all_when_last_visit_is_very_old(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'youtube.com/feeds/videos.xml*' => Http::response($this->feed([
                now()->subHours(2),
                now()->subDays(3),
                now()->subWeeks(2),
            ]), 200),
            'youtube.com/*' => Http::response($this->channelPage(), 200),
        ]);

        $profile = $this->youtubeProfile(now()->subYears(5)->toDateTimeString());

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(3, $profile->fresh()->new_items);
    }

    public function test_resolves_and_caches_channel_id_from_channel_page(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'youtube.com/feeds/videos.xml*' => Http::response($this->feed([now()->subHour()]), 200),
            'youtube.com/*' => Http::response($this->channelPage(), 200),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());
        $this->assertNull($profile->youtube_channel_id);

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(self::CHANNEL_ID, $profile->fresh()->youtube_channel_id);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/@channel/videos'));
    }

    public function test_cached_channel_id_skips_the_channel_page_request(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'youtube.com/feeds/videos.xml*' => Http::response($this->feed([now()->subHour()]), 200),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());
        $profile->forceFill(['youtube_channel_id' => self::CHANNEL_ID])->save();

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(1, $profile->fresh()->new_items);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/@channel/videos'));
    }

    public function test_incidental_sign_in_links_are_not_treated_as_blocked(): void
    {
        // A normal channel page carries many accounts.google.com sign-in links.
        // Those must not be mistaken for a consent/sign-in wall, otherwise the
        // job bails before resolving the channel id and never fetches.
        Http::preventStrayRequests();
        Http::fake([
            'youtube.com/feeds/videos.xml*' => Http::response($this->feed([now()->subHour()]), 200),
            'youtube.com/*' => Http::response($this->channelPage(), 200),
        ]);

        $this->assertStringContainsString('accounts.google.com', $this->channelPage());

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(self::CHANNEL_ID, $profile->fresh()->youtube_channel_id);
        $this->assertSame(1, $profile->fresh()->new_items);
    }

    public function test_requests_do_not_send_a_stale_static_consent_cookie(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'youtube.com/feeds/videos.xml*' => Http::response($this->feed([now()->subHour()]), 200),
            'youtube.com/*' => Http::response($this->channelPage(), 200),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());

        (new FetchYouTubeNewItemsJob($profile))->handle();

        Http::assertSent(fn (Request $request): bool => $request->header('Cookie') === []);
    }

    public function test_non_youtube_host_is_not_fetched(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $user = User::factory()->create();
        $source = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'url' => 'http://169.254.169.254/?youtube.com=1&x=/videos',
        ]);
        $profile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'new_items' => 2,
        ]);

        (new FetchYouTubeNewItemsJob($profile))->handle();

        Http::assertNothingSent();
        $this->assertSame(2, $profile->fresh()->new_items);
    }

    public function test_job_writes_new_items_when_run_through_sync_queue(): void
    {
        // No Bus::fake here: the sync queue driver runs the job for real,
        // exercising SerializesModels serialize/restore plus parsing end-to-end.
        Http::fake([
            'youtube.com/feeds/videos.xml*' => Http::response($this->feed([
                now()->subHours(2),
                now()->subDays(3),
            ]), 200),
            'youtube.com/*' => Http::response($this->channelPage(), 200),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());

        dispatch(new FetchYouTubeNewItemsJob($profile));

        $this->assertSame(2, $profile->fresh()->new_items);
    }

    public function test_channel_page_without_channel_id_leaves_new_items_unchanged(): void
    {
        Http::fake([
            'youtube.com/*' => Http::response('<html><body>no channel id here</body></html>', 200),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());
        $profile->forceFill(['new_items' => 5])->save();

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(5, $profile->fresh()->new_items);
        $this->assertNull($profile->fresh()->youtube_channel_id);
    }

    public function test_non_ok_channel_response_fails_soft(): void
    {
        Http::fake([
            'youtube.com/*' => Http::response('too many requests', 429),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());
        $profile->forceFill(['new_items' => 4])->save();

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(4, $profile->fresh()->new_items);
    }

    public function test_non_ok_feed_response_fails_soft(): void
    {
        Http::fake([
            'youtube.com/feeds/videos.xml*' => Http::response('too many requests', 429),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());
        $profile->forceFill([
            'new_items' => 4,
            'youtube_channel_id' => self::CHANNEL_ID,
        ])->save();

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(4, $profile->fresh()->new_items);
    }

    public function test_malformed_feed_leaves_new_items_unchanged(): void
    {
        Http::fake([
            'youtube.com/feeds/videos.xml*' => Http::response('<<<not valid xml', 200),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());
        $profile->forceFill([
            'new_items' => 3,
            'youtube_channel_id' => self::CHANNEL_ID,
        ])->save();

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(3, $profile->fresh()->new_items);
    }

    public function test_google_sign_in_redirect_is_detected_as_blocked(): void
    {
        Http::fake([
            'youtube.com/*' => Http::response(
                '<html><head><meta http-equiv="refresh" content="0;url=https://accounts.google.com/InteractiveLogin?continue=https://www.youtube.com/signin"></head></html>',
                200
            ),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());
        $profile->forceFill(['new_items' => 3])->save();

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(3, $profile->fresh()->new_items);
    }

    public function test_consent_wall_redirect_is_detected_as_blocked(): void
    {
        Http::fake([
            'youtube.com/*' => Http::response(
                '<html><head><link rel="canonical" href="https://consent.youtube.com/m?continue=https://www.youtube.com/"></head></html>',
                200
            ),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());
        $profile->forceFill(['new_items' => 2])->save();

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(2, $profile->fresh()->new_items);
    }

    public function test_profile_without_source_url_makes_no_request(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id, 'url' => '']);
        $profile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
        ]);

        (new FetchYouTubeNewItemsJob($profile))->handle();

        Http::assertNothingSent();
        $this->assertSame(0, $profile->fresh()->new_items);
    }

    private function youtubeProfile(string $lastVisitAt): NetworkProfile
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'url' => 'https://youtube.com/@{username}/videos',
        ]);

        return NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'username' => 'channel',
            'youtube_channel_id' => null,
            'last_visit_at' => $lastVisitAt,
            'new_items' => 0,
        ]);
    }

    private function channelPage(): string
    {
        return file_get_contents(base_path('tests/fixtures/youtube/channel_page.html'));
    }

    /**
     * Build a minimal YouTube Atom feed whose entries carry the given absolute
     * published timestamps.
     *
     * @param  list<Carbon>  $publishedAts
     */
    private function feed(array $publishedAts): string
    {
        $entries = '';

        foreach ($publishedAts as $index => $publishedAt) {
            $published = $publishedAt->toAtomString();

            $entries .= <<<XML
                <entry>
                    <id>yt:video:video{$index}</id>
                    <title>Video {$index}</title>
                    <published>{$published}</published>
                </entry>
            XML;
        }

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <feed xmlns:yt="http://www.youtube.com/xml/schemas/2015" xmlns="http://www.w3.org/2005/Atom">
            <title>Channel uploads</title>
            {$entries}
        </feed>
        XML;
    }
}
