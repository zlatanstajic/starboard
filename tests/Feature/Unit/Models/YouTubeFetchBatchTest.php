<?php

declare(strict_types=1);

namespace Tests\Feature\Unit\Models;

use App\Models\User;
use App\Models\YouTubeFetchBatch;
use App\Models\YouTubeFetchRun;
use Tests\TestCase;

class YouTubeFetchBatchTest extends TestCase
{
    public function test_runs_relation_uses_the_youtube_fetch_batch_foreign_key(): void
    {
        $user = User::factory()->create();
        $batch = YouTubeFetchBatch::factory()->create(['user_id' => $user->id]);
        YouTubeFetchRun::factory()->create([
            'youtube_fetch_batch_id' => $batch->id,
            'user_id' => $user->id,
            'outcome' => 'success',
        ]);

        $outcomes = $batch->runs()
            ->whereNotNull('outcome')
            ->selectRaw('outcome, COUNT(*) as aggregate')
            ->groupBy('outcome')
            ->pluck('aggregate', 'outcome');

        $this->assertSame(1, $outcomes['success']);
    }
}
