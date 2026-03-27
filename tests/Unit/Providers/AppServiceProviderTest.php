<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
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

    public function test_observer_not_registered_during_tests(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, NetworkSource::query()->withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->assertSame(0, NetworkTag::query()->withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->assertSame(0, NetworkProfile::query()->withoutGlobalScopes()->where('user_id', $user->id)->count());
    }

    public function test_observer_can_be_manually_registered(): void
    {
        User::observe(UserObserver::class);

        $user = User::factory()->create();

        $this->assertGreaterThan(0, NetworkSource::query()->withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->assertGreaterThan(0, NetworkTag::query()->withoutGlobalScopes()->where('user_id', $user->id)->count());
        $this->assertGreaterThan(0, NetworkProfile::query()->withoutGlobalScopes()->where('user_id', $user->id)->count());

        // Unregister to avoid affecting other tests
        User::flushEventListeners();
        User::boot();
    }
}
