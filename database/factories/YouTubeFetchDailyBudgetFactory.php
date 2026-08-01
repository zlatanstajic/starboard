<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\YouTubeFetchDailyBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YouTubeFetchDailyBudget>
 */
class YouTubeFetchDailyBudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_date' => now()->utc()->toDateString(),
            'reserved_requests' => 0,
        ];
    }
}
