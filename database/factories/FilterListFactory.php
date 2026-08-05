<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FilterListFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(3),
            'hash' => Str::random(12),
            'description' => $this->faker->optional()->sentence(),
            'filters' => ['filter' => ['is_public' => '1'], 'sort' => '-created_at'],
            'is_published' => true,
            'published_at' => now(),
        ];
    }
}
