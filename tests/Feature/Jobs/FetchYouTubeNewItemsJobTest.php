<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\FetchYouTubeNewItemsJob;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchYouTubeNewItemsJobTest extends TestCase
{
    public function test_counts_videos_published_at_or_after_last_visit(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'youtube.com/*' => Http::response($this->fixture(), 200),
        ]);

        // Fixture rows: "2 hours ago", "3 days ago", "2 weeks ago",
        // "Streamed 3 days ago", "Premiered 2 days ago". With last visit 7 days
        // ago, all but the two-weeks-old one count (including the prefixed rows).
        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(4, $profile->fresh()->new_items);
    }

    public function test_counts_all_when_last_visit_is_very_old(): void
    {
        Http::fake([
            'youtube.com/*' => Http::response($this->fixture(), 200),
        ]);

        $profile = $this->youtubeProfile(now()->subYears(5)->toDateTimeString());

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(5, $profile->fresh()->new_items);
    }

    public function test_livestream_and_premiere_prefixes_are_counted(): void
    {
        // The fixture contains "Streamed 3 days ago" and "Premiered 2 days ago".
        // Carbon cannot parse those raw strings, so reaching a count of 4 (rather
        // than 2) only happens if the "Streamed "/"Premiered " prefixes are stripped.
        Http::fake([
            'youtube.com/*' => Http::response($this->fixture(), 200),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(4, $profile->fresh()->new_items);
    }

    public function test_title_containing_semicolon_brace_does_not_break_parsing(): void
    {
        // The newest fixture video title contains a literal "};" token. A naive
        // non-greedy regex would truncate ytInitialData there and fail to parse.
        Http::fake([
            'youtube.com/*' => Http::response($this->fixture(), 200),
        ]);

        $profile = $this->youtubeProfile(now()->subHours(3)->toDateTimeString());

        (new FetchYouTubeNewItemsJob($profile))->handle();

        // Only the "2 hours ago" video is newer than a 3-hour-old last visit.
        $this->assertSame(1, $profile->fresh()->new_items);
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
            'youtube.com/*' => Http::response($this->fixture(), 200),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());

        dispatch(new FetchYouTubeNewItemsJob($profile));

        $this->assertSame(4, $profile->fresh()->new_items);
    }

    public function test_malformed_html_leaves_new_items_unchanged(): void
    {
        Http::fake([
            'youtube.com/*' => Http::response('<html><body>no data here</body></html>', 200),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());
        $profile->forceFill(['new_items' => 5])->save();

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(5, $profile->fresh()->new_items);
    }

    public function test_non_ok_response_fails_soft(): void
    {
        Http::fake([
            'youtube.com/*' => Http::response('too many requests', 429),
        ]);

        $profile = $this->youtubeProfile(now()->subDays(7)->toDateTimeString());
        $profile->forceFill(['new_items' => 4])->save();

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(4, $profile->fresh()->new_items);
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

    public function test_parses_current_lockup_view_model_markup(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'youtube.com/*' => Http::response($this->lockupFixture(), 200),
        ]);

        // Lockup fixture rows: "2 hours ago", "3 days ago", "Streamed 1 week ago".
        // With last visit 10 days ago all three count, including the streamed row,
        // which proves the current lockupViewModel markup is read correctly.
        $profile = $this->youtubeProfile(now()->subDays(10)->toDateTimeString());

        (new FetchYouTubeNewItemsJob($profile))->handle();

        $this->assertSame(3, $profile->fresh()->new_items);
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
            'last_visit_at' => $lastVisitAt,
            'new_items' => 0,
        ]);
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/fixtures/youtube/videos_page.html'));
    }

    private function lockupFixture(): string
    {
        return file_get_contents(base_path('tests/fixtures/youtube/videos_page_lockup.html'));
    }
}
