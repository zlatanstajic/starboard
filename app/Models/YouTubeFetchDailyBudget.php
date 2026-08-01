<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $reserved_requests
 * @property ?Carbon $blocked_until
 */
#[\Illuminate\Database\Eloquent\Attributes\Fillable(['budget_date', 'reserved_requests', 'blocked_until', 'block_reason'])]
#[\Illuminate\Database\Eloquent\Attributes\Table(name: 'youtube_fetch_daily_budgets')]
class YouTubeFetchDailyBudget extends Model
{
    /** @use HasFactory<\Database\Factories\YouTubeFetchDailyBudgetFactory> */
    use HasFactory;

    #[Override]
    protected function casts(): array
    {
        return ['blocked_until' => 'datetime'];
    }
}
