<?php

namespace Database\Factories;

use App\Models\NetworkSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @package Database\Factories
 */
class NetworkProfileFactory extends Factory
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
            'network_source_id' => NetworkSource::factory(),
            'username' => $this->faker->username(),
        ];
    }
}
