<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\NetworkSourcesEnum;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->seedNetworkSources($user->id);
        $this->seedNetworkTags($user->id);
        $this->seedNetworkProfiles($user->id);
    }

    /**
     * Seed network sources for the given user.
     */
    private function seedNetworkSources(int $userId): void
    {
        $sources = collect(NetworkSourcesEnum::cases())->all();

        foreach ($sources as $source) {
            NetworkSource::query()->updateOrCreate(['user_id' => $userId, 'name' => $source->name], [
                'user_id' => $userId,
                'name' => $source->name,
                'url' => $source->urlTemplate(),
            ]);
        }
    }

    /**
     * Seed network tags for the given user.
     */
    private function seedNetworkTags(int $userId): void
    {
        $tags = [
            ['name' => 'Tech', 'description' => 'Technology and Software'],
            ['name' => 'News', 'description' => 'News and Current Events'],
            ['name' => 'Sports', 'description' => 'Sports and Recreation'],
            ['name' => 'Entertainment', 'description' => 'Movies, Music, and TV Shows'],
            ['name' => 'Education', 'description' => 'Educational Content and Resources'],
        ];

        foreach ($tags as $tag) {
            NetworkTag::query()->updateOrCreate(['name' => $tag['name'], 'user_id' => $userId], [
                'user_id' => $userId,
                'description' => $tag['description'],
            ]);
        }
    }

    /**
     * Seed network profiles for the given user.
     */
    private function seedNetworkProfiles(int $userId): void
    {
        $sources = collect(NetworkSourcesEnum::cases())->all();

        foreach ($sources as $source) {
            // Get or create network source for this user
            $networkSource = NetworkSource::query()->updateOrCreate(['user_id' => $userId, 'name' => $source->name], [
                'user_id' => $userId,
                'name' => $source->name,
                'url' => $source->urlTemplate(),
            ]);

            // Create or update network profile
            NetworkProfile::query()->updateOrCreate(['user_id' => $userId, 'network_source_id' => $networkSource->id, 'username' => $source->value], [
                'user_id' => $userId,
                'network_source_id' => $networkSource->id,
                'username' => $source->value,
            ]);
        }
    }
}
