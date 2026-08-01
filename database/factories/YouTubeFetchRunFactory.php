<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NetworkProfile;
use App\Models\User;
use App\Models\YouTubeFetchRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<YouTubeFetchRun>
 */
class YouTubeFetchRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'network_profile_id' => NetworkProfile::factory(),
            'stage' => 'queued',
        ];
    }
}
