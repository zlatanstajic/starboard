<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NetworkSourcesEnum;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use Illuminate\Database\Seeder;

class NetworkProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId = User::first()?->id ?? User::factory()->create()->id;

        $data = collect(NetworkSourcesEnum::cases())->map(fn ($source) => [
            'user_id' => $userId,
            'network_source_id' => NetworkSource::firstOrCreate(
                ['name' => $source->name],
                ['url' => $source->urlTemplate()]
            )->id,
            'username' => $source->value,
        ])->toArray();

        NetworkProfile::upsert(
            $data,
            ['user_id', 'network_source_id'],
            ['username', 'updated_at']
        );
    }
}
