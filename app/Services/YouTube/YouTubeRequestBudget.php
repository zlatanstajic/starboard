<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use App\Exceptions\YouTube\YouTubeRequestBudgetExhaustedException;
use App\Models\YouTubeFetchDailyBudget;
use App\Models\YouTubeFetchRun;
use Illuminate\Support\Facades\DB;

class YouTubeRequestBudget
{
    public function reserve(string $runUuid): void
    {
        DB::transaction(function () use ($runUuid): void {
            $date = now()->utc()->toDateString();
            $dailyBudget = YouTubeFetchDailyBudget::query()->firstOrCreate(['budget_date' => $date]);
            $budget = YouTubeFetchDailyBudget::query()->whereKey($dailyBudget->getKey())->lockForUpdate()->firstOrFail();

            throw_if($budget->blocked_until?->isFuture(), YouTubeRequestBudgetExhaustedException::class, 'The shared YouTube fetch circuit is open.');

            throw_if($budget->reserved_requests >= (int) config('youtube.daily_request_limit'), YouTubeRequestBudgetExhaustedException::class, 'The daily YouTube request budget is exhausted.');

            $budget->increment('reserved_requests');
            YouTubeFetchRun::query()->where('uuid', $runUuid)->increment('request_count');
        }, 3);
    }

    public function block(string $reason): void
    {
        $budget = YouTubeFetchDailyBudget::query()->firstOrCreate(['budget_date' => now()->utc()->toDateString()]);
        $budget->update([
            'blocked_until' => now()->addSeconds((int) config('youtube.blocked_cooldown_seconds')),
            'block_reason' => mb_substr($reason, 0, 64),
        ]);
    }

    /** @return array{circuit_open: bool, budget_exhausted: bool} */
    public function availability(): array
    {
        $budget = YouTubeFetchDailyBudget::query()->where('budget_date', now()->utc()->toDateString())->first();

        if ($budget === null) {
            return ['circuit_open' => false, 'budget_exhausted' => false];
        }

        return [
            'circuit_open' => $budget->blocked_until?->isFuture() ?? false,
            'budget_exhausted' => $budget->reserved_requests >= (int) config('youtube.daily_request_limit'),
        ];
    }
}
