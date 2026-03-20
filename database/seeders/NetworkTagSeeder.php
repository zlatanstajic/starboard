<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NetworkTag;
use App\Models\User;
use Illuminate\Database\Seeder;

class NetworkTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId = User::first()?->id ?? User::factory()->create()->id;

        $tags = [
            ['name' => 'Tech', 'description' => 'Technology and Software'],
            ['name' => 'News', 'description' => 'News and Current Events'],
            ['name' => 'Sports', 'description' => 'Sports and Recreation'],
            ['name' => 'Entertainment', 'description' => 'Movies, Music, and TV Shows'],
            ['name' => 'Education', 'description' => 'Educational Content and Resources'],
        ];

        foreach ($tags as $tag) {
            NetworkTag::updateOrCreate(
                ['name' => $tag['name']],
                [
                    'user_id' => $userId,
                    'description' => $tag['description'],
                ]
            );
        }
    }
}
