<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NetworkSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'network_source_id' => NetworkSource::factory(),
            'username' => $this->faker->username(),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
