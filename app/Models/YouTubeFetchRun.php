<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $duration_ms
 * @property int|null $network_profile_id
 * @property int $request_count
 * @property int $retry_count
 * @property int|null $youtube_fetch_batch_id
 * @property string $uuid
 */
#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'uuid', 'youtube_fetch_batch_id', 'network_profile_id', 'user_id', 'stage', 'outcome',
    'http_status', 'transport', 'request_count', 'retry_count', 'duration_ms',
    'retry_delay_seconds', 'error', 'finished_at',
])]
#[\Illuminate\Database\Eloquent\Attributes\Table(name: 'youtube_fetch_runs')]
class YouTubeFetchRun extends Model
{
    /** @use HasFactory<\Database\Factories\YouTubeFetchRunFactory> */
    use HasFactory;

    public function batch(): BelongsTo
    {
        return $this->belongsTo(YouTubeFetchBatch::class, 'youtube_fetch_batch_id');
    }

    public function networkProfile(): BelongsTo
    {
        return $this->belongsTo(NetworkProfile::class);
    }

    #[Override]
    protected function casts(): array
    {
        return ['finished_at' => 'datetime'];
    }
}
