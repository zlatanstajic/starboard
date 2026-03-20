<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NetworkSourcesEnum;
use App\Models\NetworkSource;
use App\Models\User;
use Illuminate\Database\Seeder;

class NetworkSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId = User::first()?->id ?? User::factory()->create()->id;

        $data = collect(NetworkSourcesEnum::cases())->map(fn ($source) => [
            'user_id' => $userId,
            'name' => $source->name,
            'url' => $source->urlTemplate(),
        ])->toArray();

        NetworkSource::upsert($data, ['id'], ['name', 'url']);
    }
}
