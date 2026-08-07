<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Migrations;

use App\Models\NetworkSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillGithubNetworkSourceIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_all_github_sources_without_changing_unrelated_sources(): void
    {
        $authenticatedUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $githubSource = NetworkSource::factory()->create([
            'user_id' => $authenticatedUser->id,
            'url' => 'https://github.com/laravel/framework',
        ]);
        $otherUsersGithubSource = NetworkSource::factory()->create([
            'user_id' => $otherUser->id,
            'url' => 'https://laravel.github.io/framework',
        ]);
        $softDeletedGithubSource = NetworkSource::factory()->create([
            'user_id' => $otherUser->id,
            'url' => 'https://www.github.com/laravel/framework',
        ]);
        $unrelatedSource = NetworkSource::factory()->create([
            'user_id' => $authenticatedUser->id,
            'url' => 'https://notgithub.com/laravel/framework',
        ]);

        $softDeletedGithubSource->delete();

        NetworkSource::query()->withoutGlobalScopes()
            ->whereIn('id', [$githubSource->id, $otherUsersGithubSource->id, $softDeletedGithubSource->id])
            ->toBase()
            ->update(['icon' => 'imdb']);
        NetworkSource::query()->withoutGlobalScopes()
            ->whereKey($unrelatedSource->id)
            ->toBase()
            ->update(['icon' => 'facebook']);

        $this->actingAs($authenticatedUser);

        $migration = require database_path('migrations/2026_08_07_190548_backfill_github_network_source_icons.php');
        $migration->up();

        $this->assertSame('github', $this->iconFor($githubSource));
        $this->assertSame('github', $this->iconFor($otherUsersGithubSource));
        $this->assertSame('github', $this->iconFor($softDeletedGithubSource));
        $this->assertSame('facebook', $this->iconFor($unrelatedSource));
    }

    private function iconFor(NetworkSource $networkSource): ?string
    {
        return NetworkSource::query()->withoutGlobalScopes()
            ->whereKey($networkSource->getKey())
            ->value('icon');
    }
}
