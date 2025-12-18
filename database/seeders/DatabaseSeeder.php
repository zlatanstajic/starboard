<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * @package Database\Seeders
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(NetworkSourceSeeder::class);
        $this->call(NetworkProfileSeeder::class);
    }
}
