<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataTransferObjects\YouTube\YouTubeProfileResult;
use App\Enums\YouTubeFetchOutcome;
use App\Models\NetworkProfile;
use App\Models\YouTubeFetchRun;
use App\Services\YouTube\YouTubeVideoFetchService;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FetchYouTubeNewItemsJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout;

    public int $tries;

    public readonly int $networkProfileId;

    public readonly int $userId;

    public readonly string $runUuid;

    public function __construct(NetworkProfile|int $networkProfile, ?int $userId = null, ?string $runUuid = null)
    {
        $this->networkProfileId = $networkProfile instanceof NetworkProfile ? (int) $networkProfile->getKey() : $networkProfile;
        $this->userId = $networkProfile instanceof NetworkProfile ? (int) $networkProfile->user_id : (int) $userId;
        $this->runUuid = $runUuid ?? (string) Str::uuid();
        $this->timeout = (int) config('youtube.job_timeout', 45);
        $this->tries = (int) config('youtube.retry.attempts', 3) + 1;
        $this->onQueue((string) config('youtube.queue', 'youtube'));
    }

    public function handle(?YouTubeVideoFetchService $service = null): void
    {
        $run = YouTubeFetchRun::query()->firstOrCreate(
            ['uuid' => $this->runUuid],
            [
                'network_profile_id' => $this->networkProfileId,
                'user_id' => $this->userId,
                'stage' => 'running',
            ],
        );

        if (! config('youtube.execution_enabled')) {
            $this->completeRun($run, new YouTubeProfileResult(YouTubeFetchOutcome::Disabled, 'configuration'));

            return;
        }

        $result = ($service ?? resolve(YouTubeVideoFetchService::class))->fetch($this->networkProfileId, $this->userId, $this->runUuid);

        if ($result->outcome->retryable() && $run->retry_count < (int) config('youtube.retry.attempts') && $this->job !== null) {
            $delay = $this->retryDelay($result, $run->retry_count + 1);
            $run->update([
                'stage' => $result->stage,
                'outcome' => YouTubeFetchOutcome::Retrying->value,
                'http_status' => $result->status,
                'transport' => $result->transport,
                'duration_ms' => $run->duration_ms + $result->durationMilliseconds,
                'retry_count' => $run->retry_count + 1,
                'retry_delay_seconds' => $delay,
                'error' => $this->sanitize($result->error),
            ]);
            $this->log($run, $result, $delay);
            $this->release($delay);

            return;
        }

        $this->completeRun($run, $result);
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addSeconds(max($this->timeout + 1, ((int) config('youtube.retry.ceiling_seconds') * $this->tries) + $this->timeout));
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping("youtube:{$this->userId}:{$this->networkProfileId}")
            ->releaseAfter(15)
            ->expireAfter($this->timeout + 15)];
    }

    public function failed(?Throwable $exception): void
    {
        $run = YouTubeFetchRun::query()->where('uuid', $this->runUuid)->first();

        if ($run !== null) {
            $this->completeRun($run, new YouTubeProfileResult(
                YouTubeFetchOutcome::UnexpectedFailure,
                'failed',
                error: $exception?->getMessage(),
            ));
        }
    }

    private function completeRun(YouTubeFetchRun $run, YouTubeProfileResult $result): void
    {
        $run->update([
            'stage' => $result->stage,
            'outcome' => $result->outcome->value,
            'http_status' => $result->status,
            'transport' => $result->transport,
            'duration_ms' => $run->duration_ms + $result->durationMilliseconds,
            'retry_delay_seconds' => null,
            'error' => $this->sanitize($result->error),
            'finished_at' => now(),
        ]);
        $this->log($run, $result);
    }

    private function retryDelay(YouTubeProfileResult $result, int $attempt): int
    {
        if ($result->retryAfterSeconds !== null) {
            return min($result->retryAfterSeconds, (int) config('youtube.retry.max_retry_after'));
        }

        $maximum = min(
            (int) config('youtube.retry.ceiling_seconds'),
            (int) config('youtube.retry.base_seconds') * ((int) config('youtube.retry.factor') ** max(0, $attempt - 1)),
        );

        return random_int(0, max(0, $maximum));
    }

    private function sanitize(?string $error): ?string
    {
        if ($error === null) {
            return null;
        }

        return mb_substr(preg_replace('/https?:\/\/\S+/i', '[url]', $error) ?? '', 0, 500);
    }

    private function log(YouTubeFetchRun $run, YouTubeProfileResult $result, ?int $delay = null): void
    {
        Log::channel((string) config('youtube.logging_channel'))->info('YouTube fetch run updated.', [
            'run_uuid' => $run->uuid,
            'batch_id' => $run->youtube_fetch_batch_id,
            'profile_id' => $run->network_profile_id,
            'stage' => $result->stage,
            'outcome' => $delay === null ? $result->outcome->value : YouTubeFetchOutcome::Retrying->value,
            'status' => $result->status,
            'transport' => $result->transport,
            'duration_ms' => $result->durationMilliseconds,
            'request_count' => $run->request_count,
            'retry_count' => $run->retry_count,
            'retry_delay_seconds' => $delay,
        ]);
    }
}
