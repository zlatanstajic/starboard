<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\YouTubeFetchBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YouTubeFetchBatch>
 */
class YouTubeFetchBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'filters' => [],
            'state' => 'preparing',
            'total' => 0,
        ];
    }
}
