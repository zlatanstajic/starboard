<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property string|null $laravel_batch_id
 */
#[\Illuminate\Database\Eloquent\Attributes\Fillable(['user_id', 'laravel_batch_id', 'filters', 'state', 'total', 'started_at', 'finished_at'])]
#[\Illuminate\Database\Eloquent\Attributes\Table(name: 'youtube_fetch_batches')]
class YouTubeFetchBatch extends Model
{
    /** @use HasFactory<\Database\Factories\YouTubeFetchBatchFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(YouTubeFetchRun::class, 'youtube_fetch_batch_id');
    }

    #[Override]
    protected function casts(): array
    {
        return ['filters' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
