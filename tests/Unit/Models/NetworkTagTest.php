<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\NetworkProfile;
use App\Models\NetworkTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class NetworkTagTest extends TestCase
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

    public function test_force_delete_detaches_pivot_rows()
    {
        $user = User::factory()->create();

        $profile = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $tag = NetworkTag::factory()->create(['user_id' => $user->id]);

        // attach pivot
        $tag->networkProfiles()->attach($profile->id);

        $this->assertDatabaseHas('network_profile_network_tag', [
            'network_profile_id' => $profile->id,
            'network_tag_id' => $tag->id,
        ]);

        // force delete should trigger the deleting event and detach pivot rows
        $tag->forceDelete();

        $this->assertDatabaseMissing('network_profile_network_tag', [
            'network_profile_id' => $profile->id,
            'network_tag_id' => $tag->id,
        ]);

        $this->assertDatabaseMissing('network_tags', ['id' => $tag->id]);
    }
}
