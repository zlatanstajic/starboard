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
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    #[Override]
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_created_seeds_sources_tags_and_profiles_for_user(): void
    {
        $user = User::factory()->create();

        $observer = new UserObserver;
        $observer->created($user);

        $sourceCount = count(NetworkSourcesEnum::cases());

        $this->assertEquals($sourceCount, NetworkSource::query()->where('user_id', $user->id)->count());
        $this->assertEquals(5, NetworkTag::query()->where('user_id', $user->id)->count());
        $this->assertEquals($sourceCount, NetworkProfile::query()->where('user_id', $user->id)->count());
    }
}
