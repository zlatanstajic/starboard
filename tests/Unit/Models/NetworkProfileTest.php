<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NetworkProfileTest extends TestCase
{
    public function test_cast_attributes_to_boolean(): void
    {
        $networkProfile = NetworkProfile::factory()->create([
            'is_public' => true,
            'is_favorite' => false,
        ]);

        $this->assertIsBool($networkProfile->is_public);
        $this->assertTrue($networkProfile->is_public);
        $this->assertIsBool($networkProfile->is_favorite);
        $this->assertFalse($networkProfile->is_favorite);
    }

    public function test_last_visit_at_is_cast_to_datetime(): void
    {
        $networkProfile = NetworkProfile::factory()->create([
            'last_visit_at' => '2025-06-15 10:30:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $networkProfile->last_visit_at);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $networkProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $networkProfile->user());
        $this->assertInstanceOf(User::class, $networkProfile->user);
        $this->assertSame($user->id, $networkProfile->user->id);
    }

    public function test_belongs_to_network_source(): void
    {
        $networkSource = NetworkSource::factory()->create();
        $networkProfile = NetworkProfile::factory()->create([
            'network_source_id' => $networkSource->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $networkProfile->networkSource());
        $this->assertInstanceOf(NetworkSource::class, $networkProfile->networkSource);
        $this->assertSame($networkSource->id, $networkProfile->networkSource->id);
    }

    public function test_belongs_to_many_network_tags(): void
    {
        $user = User::factory()->create();
        $networkProfile = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $tags = NetworkTag::factory()->count(2)->create(['user_id' => $user->id]);

        $networkProfile->networkTags()->attach($tags->pluck('id'));

        $this->assertInstanceOf(BelongsToMany::class, $networkProfile->networkTags());
        $this->assertCount(2, $networkProfile->fresh()->networkTags);
    }

    public function test_user_scope_filters_records_by_authenticated_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        NetworkProfile::factory()->create(['user_id' => $user1->id]);
        NetworkProfile::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);

        $profiles = NetworkProfile::query()->get();

        $this->assertGreaterThan(0, $profiles->count());
        $profiles->each(fn (NetworkProfile $profile) => $this->assertSame($user1->id, $profile->user_id));
    }

    public function test_date_shortener_formats_dates_correctly(): void
    {
        $networkProfile = NetworkProfile::factory()->create([
            'last_visit_at' => '2024-01-15 10:30:00',
            'created_at' => '2023-12-20 14:45:00',
            'updated_at' => '2024-02-10 09:15:00',
        ]);

        $this->assertIsString($networkProfile->last_visit_short);
        $this->assertIsString($networkProfile->created_at_short);
        $this->assertIsString($networkProfile->updated_at_short);
    }

    public function test_profile_url_replaces_username_when_source_present(): void
    {
        $source = new NetworkSource;
        $source->url = 'https://example.com/{username}';

        $profile = new NetworkProfile;
        $profile->username = 'john_doe';
        $profile->setRelation('networkSource', $source);

        $this->assertSame('https://example.com/john_doe', $profile->profileUrl());
    }

    public function test_profile_url_replaces_id_placeholder(): void
    {
        $source = new NetworkSource;
        $source->url = 'https://example.com/user/{id}';

        $profile = new NetworkProfile;
        $profile->username = '12345';
        $profile->setRelation('networkSource', $source);

        $this->assertSame('https://example.com/user/12345', $profile->profileUrl());
    }

    public function test_profile_url_replaces_hash_placeholder(): void
    {
        $source = new NetworkSource;
        $source->url = 'https://example.com/p/{hash}';

        $profile = new NetworkProfile;
        $profile->username = 'abc123';
        $profile->setRelation('networkSource', $source);

        $this->assertSame('https://example.com/p/abc123', $profile->profileUrl());
    }

    public function test_profile_url_replaces_uuid_placeholder(): void
    {
        $source = new NetworkSource;
        $source->url = 'https://example.com/u/{uuid}';

        $profile = new NetworkProfile;
        $profile->username = '550e8400-e29b-41d4-a716-446655440000';
        $profile->setRelation('networkSource', $source);

        $this->assertSame('https://example.com/u/550e8400-e29b-41d4-a716-446655440000', $profile->profileUrl());
    }

    public function test_profile_url_returns_empty_when_no_source(): void
    {
        $profile = new NetworkProfile;
        $profile->username = 'someone';

        $this->assertSame('', $profile->profileUrl());
    }

    public function test_profile_url_returns_empty_when_source_url_is_null(): void
    {
        $source = new NetworkSource;
        $source->url = null;

        $profile = new NetworkProfile;
        $profile->username = 'someone';
        $profile->setRelation('networkSource', $source);

        $this->assertSame('', $profile->profileUrl());
    }
}
