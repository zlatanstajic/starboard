<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Enums\NetworkSourcesEnum;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class UserObserverTest extends TestCase
{
    private UserObserver $observer;

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->observer = new UserObserver;
        $this->user = User::factory()->create();
    }

    #[Override]
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_created_seeds_network_sources_for_user(): void
    {
        $this->observer->created($this->user);

        $sources = NetworkSource::query()->withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->get();

        $this->assertCount(count(NetworkSourcesEnum::cases()), $sources);

        foreach (NetworkSourcesEnum::cases() as $case) {
            $source = $sources->firstWhere('name', $case->name);
            $this->assertNotNull($source, "Source '{$case->name}' should exist");
            $this->assertSame($case->urlTemplate(), $source->url);
        }
    }

    public function test_created_seeds_network_tags_for_user(): void
    {
        $this->observer->created($this->user);

        $tags = NetworkTag::query()->withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->get();

        $this->assertCount(5, $tags);

        $expectedTags = ['Tech', 'News', 'Sports', 'Entertainment', 'Education'];
        foreach ($expectedTags as $tagName) {
            $this->assertNotNull($tags->firstWhere('name', $tagName), "Tag '{$tagName}' should exist");
        }
    }

    public function test_created_seeds_network_profiles_for_user(): void
    {
        $this->observer->created($this->user);

        $profiles = NetworkProfile::query()->withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->get();

        $this->assertCount(count(NetworkSourcesEnum::cases()), $profiles);

        foreach (NetworkSourcesEnum::cases() as $case) {
            $this->assertNotNull(
                $profiles->firstWhere('username', $case->value),
                "Profile with username '{$case->value}' should exist"
            );
        }
    }

    public function test_created_is_idempotent(): void
    {
        $this->observer->created($this->user);
        $this->observer->created($this->user);

        $sourceCount = NetworkSource::query()->withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->count();

        $profileCount = NetworkProfile::query()->withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->count();

        $tagCount = NetworkTag::query()->withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->count();

        $this->assertSame(count(NetworkSourcesEnum::cases()), $sourceCount);
        $this->assertSame(count(NetworkSourcesEnum::cases()), $profileCount);
        $this->assertSame(5, $tagCount);
    }

    public function test_created_does_not_affect_other_users(): void
    {
        $otherUser = User::factory()->create();

        $this->observer->created($this->user);

        $this->assertSame(0, NetworkSource::query()->withoutGlobalScopes()->where('user_id', $otherUser->id)->count());
        $this->assertSame(0, NetworkTag::query()->withoutGlobalScopes()->where('user_id', $otherUser->id)->count());
        $this->assertSame(0, NetworkProfile::query()->withoutGlobalScopes()->where('user_id', $otherUser->id)->count());
    }
}
