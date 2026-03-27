<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\NetworkProfile;
use App\Models\NetworkTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function test_factory_can_create_network_tag(): void
    {
        $tag = NetworkTag::factory()->create();

        $this->assertInstanceOf(NetworkTag::class, $tag);
        $this->assertDatabaseHas('network_tags', ['id' => $tag->id]);
    }

    public function test_belongs_to_many_network_profiles(): void
    {
        $user = User::factory()->create();
        $tag = NetworkTag::factory()->create(['user_id' => $user->id]);
        $profiles = NetworkProfile::factory()->count(2)->create(['user_id' => $user->id]);

        $tag->networkProfiles()->attach($profiles->pluck('id'));

        $this->assertInstanceOf(BelongsToMany::class, $tag->networkProfiles());
        $this->assertCount(2, $tag->fresh()->networkProfiles);
    }

    public function test_soft_delete_does_not_detach_pivot_rows(): void
    {
        $user = User::factory()->create();
        $tag = NetworkTag::factory()->create(['user_id' => $user->id]);
        $profile = NetworkProfile::factory()->create(['user_id' => $user->id]);

        $tag->networkProfiles()->attach($profile->id);

        $tag->delete();

        $this->assertSoftDeleted('network_tags', ['id' => $tag->id]);
        $this->assertDatabaseHas('network_profile_network_tag', [
            'network_profile_id' => $profile->id,
            'network_tag_id' => $tag->id,
        ]);
    }

    public function test_force_delete_detaches_pivot_rows(): void
    {
        $user = User::factory()->create();
        $profile = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $tag = NetworkTag::factory()->create(['user_id' => $user->id]);

        $tag->networkProfiles()->attach($profile->id);

        $this->assertDatabaseHas('network_profile_network_tag', [
            'network_profile_id' => $profile->id,
            'network_tag_id' => $tag->id,
        ]);

        $tag->forceDelete();

        $this->assertDatabaseMissing('network_profile_network_tag', [
            'network_profile_id' => $profile->id,
            'network_tag_id' => $tag->id,
        ]);

        $this->assertDatabaseMissing('network_tags', ['id' => $tag->id]);
    }
}
