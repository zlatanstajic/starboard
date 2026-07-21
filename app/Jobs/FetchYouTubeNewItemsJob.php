<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NetworkProfile;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FetchYouTubeNewItemsJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * A realistic browser User-Agent so YouTube returns the full
     * server-rendered HTML shell (including the ytInitialData blob)
     * rather than a consent wall or a 429.
     */
    private const string USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * Relative-time prefixes YouTube adds for livestreams/premieres that
     * Carbon cannot parse and must be stripped first.
     *
     * @var list<string>
     */
    private const array TIME_PREFIXES = [
        'Streamed ',
        'Premiered ',
    ];

    /**
     * The number of seconds the job may run before timing out.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly NetworkProfile $networkProfile
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $url = $this->networkProfile->profileUrl();

        if (! $this->isValidYouTubeUrl($url)) {
            Log::warning('FetchYouTubeNewItemsJob skipped invalid URL.', [
                'network_profile_id' => $this->networkProfile->id,
                'url' => $url,
            ]);

            return;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept-Language' => 'en',
            ])->timeout(15)->get($url, ['hl' => 'en']);
        } catch (Throwable $e) {
            Log::warning('FetchYouTubeNewItemsJob request failed.', [
                'network_profile_id' => $this->networkProfile->id,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if (! $response->ok()) {
            Log::warning('FetchYouTubeNewItemsJob received non-OK response.', [
                'network_profile_id' => $this->networkProfile->id,
                'url' => $url,
                'status' => $response->status(),
            ]);

            return;
        }

        $publishedTimeTexts = $this->parsePublishedTimeTexts($response->body());

        if ($publishedTimeTexts === null) {
            Log::warning('FetchYouTubeNewItemsJob could not parse ytInitialData.', [
                'network_profile_id' => $this->networkProfile->id,
                'url' => $url,
            ]);

            return;
        }

        $count = $this->countNewItems($publishedTimeTexts);

        if ($count !== $this->networkProfile->new_items) {
            $this->networkProfile->update(['new_items' => $count]);
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
     * Extract each video's relative published time from the page's ytInitialData
     * JSON blob. Returns null when the blob is absent or cannot be decoded so
     * the caller can fail soft (leave new_items unchanged).
     *
     * @return list<string>|null
     */
    private function parsePublishedTimeTexts(string $html): ?array
    {
        if (! preg_match('/ytInitialData\s*=\s*(\{.*\})\s*;\s*<\/script>/s', $html, $matches)) {
            return null;
        }

        $data = json_decode($matches[1], true);

        if (! is_array($data)) {
            return null;
        }

        $tabs = $data['contents']['twoColumnBrowseResultsRenderer']['tabs'] ?? null;

        if (! is_array($tabs)) {
            return null;
        }

        $texts = [];

        foreach ($tabs as $tab) {
            $contents = $tab['tabRenderer']['content']['richGridRenderer']['contents'] ?? null;

            if (! is_array($contents)) {
                continue;
            }

            foreach ($contents as $item) {
                $content = $item['richItemRenderer']['content'] ?? null;

                if (! is_array($content)) {
                    continue;
                }

                $text = $this->extractPublishedTime($content);

                if ($text !== null) {
                    $texts[] = $text;
                }
            }
        }

        return $texts;
    }

    /**
     * Extract a single video's relative published time from a rich-grid item's
     * content, supporting both YouTube's current lockupViewModel markup and the
     * legacy videoRenderer markup it still serves in some regions/experiments.
     *
     * @param  array<string, mixed>  $content
     */
    private function extractPublishedTime(array $content): ?string
    {
        $legacy = $content['videoRenderer']['publishedTimeText']['runs'][0]['text'] ?? null;

        if (is_string($legacy) && $legacy !== '') {
            return $legacy;
        }

        $rows = $content['lockupViewModel']['metadata']['lockupMetadataViewModel']['metadata']['contentMetadataViewModel']['metadataRows'] ?? null;

        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            $parts = $row['metadataParts'] ?? null;

            if (! is_array($parts)) {
                continue;
            }

            foreach ($parts as $part) {
                $text = $part['text']['content'] ?? null;

                if (is_string($text) && $this->looksLikeRelativeTime($text)) {
                    return $text;
                }
            }
        }

        return null;
    }

    /**
     * Whether the given text is a relative published-time string (e.g.
     * "2 hours ago", "Streamed 1 week ago"), distinguishing it from the
     * sibling view-count metadata part ("102K views").
     */
    private function looksLikeRelativeTime(string $text): bool
    {
        return preg_match('/\d+\s+(second|minute|hour|day|week|month|year)s?\s+ago/i', $text) === 1;
    }

    /**
     * Count how many videos were published at/after the profile's last visit.
     *
     * @param  list<string>  $publishedTimeTexts
     */
    private function countNewItems(array $publishedTimeTexts): int
    {
        $lastVisitAt = $this->networkProfile->last_visit_at;

        if ($lastVisitAt === null) {
            return count($publishedTimeTexts);
        }

        $count = 0;

        foreach ($publishedTimeTexts as $text) {
            $normalized = Str::of($text)->trim();

            foreach (self::TIME_PREFIXES as $prefix) {
                $normalized = $normalized->replaceStart($prefix, '');
            }

            try {
                $publishedAt = \Illuminate\Support\Facades\Date::parse((string) $normalized);
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
