<?php

namespace Database\Seeders;

use App\Enums\NetworkSourcesEnum;
use App\Models\NetworkProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * @package Database\Seeders
 */
class NetworkProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $userId = User::first()?->getId() ?? User::factory()->create()->getId();

        $data = collect(NetworkSourcesEnum::cases())->map(fn($source) => [
            'user_id'           => $userId,
            'network_source_id' => $source->value,
            'username'          => $source->name,
        ])->toArray();

        NetworkProfile::upsert(
            $data,
            ['user_id', 'network_source_id'],
            ['username', 'updated_at']
        );
    }
}
