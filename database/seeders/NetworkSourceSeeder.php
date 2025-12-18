<?php

namespace Database\Seeders;

use App\Enums\NetworkSourcesEnum;
use App\Models\NetworkSource;
use Illuminate\Database\Seeder;

/**
 * @package Database\Seeders
 */
class NetworkSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $data = collect(NetworkSourcesEnum::cases())->map(fn($source) => [
            'id'   => $source->value,
            'name' => $source->name,
            'url'  => $source->urlTemplate(),
        ])->toArray();

        NetworkSource::upsert($data, ['id'], ['name', 'url']);
    }
}
