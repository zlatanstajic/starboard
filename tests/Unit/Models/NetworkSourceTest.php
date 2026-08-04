<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\DatabaseTableNamesEnum;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class NetworkSourceTest extends TestCase
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

    public function test_allowed_sorts_expose_the_listing_sort_options(): void
    {
        $this->assertSame([
            'name',
            'url',
            'exclude_from_dashboard',
            'network_profiles_count',
            'created_at',
            'updated_at',
        ], NetworkSource::ALLOWED_SORTS);
    }

    public function test_allowed_filters_and_includes_stay_empty(): void
    {
        // Search is a callback filter registered in the repository, not a column filter.
        $this->assertSame([], NetworkSource::ALLOWED_FILTERS);
        $this->assertSame([], NetworkSource::ALLOWED_INCLUDES);
    }

    public function test_factory_can_create_network_source(): void
    {
        $networkSource = NetworkSource::factory()->create();

        $this->assertInstanceOf(NetworkSource::class, $networkSource);
        $this->assertDatabaseHas(DatabaseTableNamesEnum::network_sources->value, [
            'id' => $networkSource->id,
        ]);
    }

    public function test_has_many_network_profiles(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);
        NetworkProfile::factory()->count(3)->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $source->networkProfiles());
        $this->assertCount(3, $source->networkProfiles);
    }

    public function test_exclude_from_dashboard_is_cast_to_boolean(): void
    {
        $source = NetworkSource::factory()->create(['exclude_from_dashboard' => 1]);

        $this->assertIsBool($source->exclude_from_dashboard);
        $this->assertTrue($source->exclude_from_dashboard);
    }

    public function test_deleting_source_soft_deletes_associated_profiles(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);
        $profile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
        ]);

        $source->delete();

        $this->assertSoftDeleted('network_sources', ['id' => $source->id]);
        $this->assertSoftDeleted('network_profiles', ['id' => $profile->id]);
    }

    public function test_icon_is_not_mass_assignable(): void
    {
        $source = NetworkSource::factory()->create();

        $this->assertNotContains('icon', $source->getFillable());
    }

    public function test_icon_is_not_overridden_by_mass_assignment(): void
    {
        $source = NetworkSource::factory()->create([
            'url' => 'https://youtube.com/@channel/videos',
            'icon' => 'facebook',
        ]);

        $this->assertSame('youtube', $source->icon);
    }

    public function test_icon_is_derived_from_url_on_create(): void
    {
        $source = NetworkSource::factory()->create([
            'url' => 'https://youtube.com/@channel/videos',
        ]);

        $this->assertSame('youtube', $source->icon);
    }

    public function test_icon_is_null_for_unknown_url_on_create(): void
    {
        $source = NetworkSource::factory()->create([
            'url' => 'https://example.com/profile',
        ]);

        $this->assertNull($source->icon);
    }

    public function test_icon_is_recomputed_when_url_changes(): void
    {
        $source = NetworkSource::factory()->create([
            'url' => 'https://youtube.com/@channel/videos',
        ]);

        $this->assertSame('youtube', $source->icon);

        $source->update(['url' => 'https://rumble.com/c/channel']);

        $this->assertSame('rumble', $source->fresh()->icon);
    }

    public function test_restoring_source_restores_soft_deleted_profiles(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);
        $profile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
        ]);

        $source->delete();

        $this->assertSoftDeleted('network_profiles', ['id' => $profile->id]);

        $source->restore();

        $this->assertNotSoftDeleted('network_sources', ['id' => $source->id]);
        $this->assertNotSoftDeleted('network_profiles', ['id' => $profile->id]);
    }
}
