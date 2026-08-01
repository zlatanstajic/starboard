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

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('youtube.execution_enabled', true);
        YouTubeFetchDailyBudget::query()->delete();
    }

    /** @return array<string, array{string}> */
    public static function invalidFeedProvider(): array
    {
        return [
            'wrong root' => ['<rss><channel /></rss>'],
            'malformed XML' => ['<<<not XML'],
            'invalid timestamp' => [self::feed(['not-a-timestamp'])],
        ];
    }

    public function test_unsafe_redirect_is_rejected_before_a_second_request(): void
    {
        [$profile, $run] = $this->profileAndRun(newItems: 9);
        $transport = new ScriptedYouTubeTransport([
            $this->response(302, headers: ['location' => ['http://169.254.169.254/latest/meta-data']]),
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::UnsafeRedirect, $result->outcome);
        $this->assertCount(1, $transport->requests);
        $this->assertSame(9, $profile->fresh()->new_items);
        $this->assertSame(1, $run->fresh()->request_count);
    }

    public function test_valid_empty_atom_feed_is_a_successful_zero(): void
    {
        [$profile, $run] = $this->profileAndRun(newItems: 6);
        $transport = new ScriptedYouTubeTransport([$this->response(200, $this->feed())]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::Success, $result->outcome);
        $this->assertSame(0, $profile->fresh()->new_items);
    }

    #[DataProvider('invalidFeedProvider')]
    public function test_invalid_feeds_preserve_the_previous_count(string $body): void
    {
        [$profile, $run] = $this->profileAndRun(newItems: 7);
        $transport = new ScriptedYouTubeTransport([$this->response(200, $body)]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::MalformedFeed, $result->outcome);
        $this->assertSame(7, $profile->fresh()->new_items);
    }

    public function test_cached_channel_id_skips_the_channel_page(): void
    {
        [$profile, $run] = $this->profileAndRun();
        $transport = new ScriptedYouTubeTransport([$this->response(200, $this->feed([now()->subMinute()->toAtomString()]))]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::Success, $result->outcome);
        $this->assertCount(1, $transport->requests);
        $this->assertStringContainsString('/feeds/videos.xml', $transport->requests[0]->url);
    }

    public function test_visit_race_recomputes_against_latest_last_visit(): void
    {
        [$profile, $run] = $this->profileAndRun(lastVisitAt: now()->subDay(), newItems: 4);
        $publishedAt = now()->subHour()->toAtomString();
        $transport = new ScriptedYouTubeTransport([
            function () use ($profile, $publishedAt): YouTubeFetchResult {
                NetworkProfile::query()->withoutGlobalScopes()->whereKey($profile->id)->update(['last_visit_at' => now()]);

                return $this->response(200, $this->feed([$publishedAt]));
            },
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::Success, $result->outcome);
        $this->assertSame(0, $profile->fresh()->new_items);
    }

    public function test_profile_edit_race_discards_the_result(): void
    {
        [$profile, $run] = $this->profileAndRun(newItems: 4);
        $transport = new ScriptedYouTubeTransport([
            function () use ($profile): YouTubeFetchResult {
                NetworkProfile::query()->withoutGlobalScopes()->whereKey($profile->id)->update(['username' => 'edited-during-fetch']);

                return $this->response(200, $this->feed([now()->subMinute()->toAtomString()]));
            },
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::StaleProfile, $result->outcome);
        $this->assertSame(4, $profile->fresh()->new_items);
    }

    public function test_blocked_response_is_terminal_and_opens_shared_cooldown(): void
    {
        [$profile, $run] = $this->profileAndRun(newItems: 5);
        $body = '<html><head><link rel="canonical" href="https://consent.youtube.com/m"></head></html>';
        $transport = new ScriptedYouTubeTransport([$this->response(200, $body)]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::ConsentRequired, $result->outcome);
        $this->assertFalse($result->outcome->retryable());
        $this->assertCount(1, $transport->requests);
        $this->assertTrue(YouTubeFetchDailyBudget::query()->firstOrFail()->blocked_until->isFuture());
        $this->assertSame(5, $profile->fresh()->new_items);
    }

    public function test_every_redirect_request_is_reserved_and_counted(): void
    {
        [$profile, $run] = $this->profileAndRun();
        $transport = new ScriptedYouTubeTransport([
            $this->response(302, headers: ['location' => ['https://www.youtube.com/feeds/videos.xml?channel_id='.self::CHANNEL_ID]]),
            $this->response(200, $this->feed()),
        ]);

        $result = $this->service($transport)->fetch($profile->id, $profile->user_id, $run->uuid);

        $this->assertSame(YouTubeFetchOutcome::Success, $result->outcome);
        $this->assertSame(2, $result->requestCount);
        $this->assertSame(2, $run->fresh()->request_count);
        $this->assertSame(2, YouTubeFetchDailyBudget::query()->firstOrFail()->reserved_requests);
    }

    /** @param list<string> $timestamps */
    private static function feed(array $timestamps = []): string
    {
        $entries = '';

        foreach ($timestamps as $timestamp) {
            $entries .= "<entry><published>{$timestamp}</published></entry>";
        }

        return '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom">'.$entries.'</feed>';
    }

    private function service(YouTubeTransport $transport): YouTubeVideoFetchService
    {
        return new YouTubeVideoFetchService($transport, new YouTubeRequestBudget);
    }

    /** @return array{NetworkProfile, YouTubeFetchRun} */
    private function profileAndRun(?Carbon $lastVisitAt = null, int $newItems = 0): array
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'url' => 'https://youtube.com/@{username}/videos',
        ]);
        $profile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'username' => 'scripted-'.uniqid(),
            'youtube_channel_id' => self::CHANNEL_ID,
            'last_visit_at' => $lastVisitAt ?? now()->subDay(),
            'new_items' => $newItems,
        ]);
        $run = YouTubeFetchRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'network_profile_id' => $profile->id,
            'user_id' => $user->id,
            'stage' => 'test',
        ]);

        return [$profile, $run];
    }

    /** @param array<string, list<string>> $headers */
    private function response(int $status, string $body = '', array $headers = []): YouTubeFetchResult
    {
        return new YouTubeFetchResult($status, $body, $headers, 'https://www.youtube.com/test', 'scripted', 1, 1, null);
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
