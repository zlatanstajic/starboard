<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Models\NetworkSource;
use App\Models\User;
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

        // Observer should not run during unit tests; no network sources should be auto-seeded
        $this->assertEquals(0, NetworkSource::query()->where('user_id', $user->id)->count());
    }
}
