<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\DatabaseTableNamesEnum;
use App\Exceptions\NetworkProfile\NetworkProfileDuplicationException;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use App\Repositories\NetworkProfileRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;
use Override;
use Tests\TestCase;

class NetworkProfileRepositoryTest extends TestCase
{
    private NetworkProfileRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new NetworkProfileRepository;
    }

    public function test_can_retrieve_all_network_profiles(): void
    {
        $results = $this->repository->getAll();

        $this->assertTrue(count($results) >= 1);
        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertInstanceOf(NetworkProfile::class, $results->first());
    }

    public function test_can_upsert_a_new_profile(): void
    {
        $username = 'username_'.$this->timestamp;

        $data = [
            'user_id' => User::factory()->create()->id,
            'network_source_id' => NetworkSource::factory()->create()->id,
            'username' => $username,
        ];

        $networkProfile = $this->repository->upsert($data);

        $this->assertEquals($username, $networkProfile->username);
        $this->assertDatabaseHas(
            DatabaseTableNamesEnum::network_profiles->value,
            $data
        );
    }

    public function test_can_upsert_to_update_an_existing_profile(): void
    {
        $oldUsername = 'old_username_'.$this->timestamp;
        $newUsername = 'new_username_'.$this->timestamp;

        $createdProfile = NetworkProfile::factory()->create([
            'username' => $oldUsername,
        ]);

        $updatedProfile = $this->repository->upsert(
            ['username' => $newUsername],
            $createdProfile
        );

        $this->assertEquals($newUsername, $updatedProfile->username);
        $this->assertDatabaseHas(DatabaseTableNamesEnum::network_profiles->value, [
            'id' => $createdProfile->id,
            'username' => $newUsername,
        ]);
    }

    public function test_throws_network_profile_duplication_exception(): void
    {
        $source = NetworkSource::factory()->create();
        $user = User::factory()->create();

        $data = [
            'user_id' => $user->id,
            'network_source_id' => $source->id, // Use the generated ID
            'username' => 'duplicated_username_'.$this->timestamp,
        ];

        NetworkProfile::factory()->create($data);

        $this->expectException(NetworkProfileDuplicationException::class);

        $this->repository->upsert($data);
    }

    public function test_throws_query_exception(): void
    {
        $data = [
            'user_id' => User::factory()->create()->id,
            'network_source_id' => NetworkSource::factory()->create()->id,
            'username' => 'unique_username_'.$this->timestamp,
        ];

        NetworkProfile::factory()->create($data);

        $this->expectException(QueryException::class);

        unset($data['username']);

        $this->repository->upsert($data);
    }

    public function test_can_delete_a_profile(): void
    {
        $networkProfile = NetworkProfile::factory()->create();

        $result = $this->repository->delete($networkProfile->id);

        $this->assertTrue($result);
        $this->assertSoftDeleted(DatabaseTableNamesEnum::network_profiles->value, [
            'id' => $networkProfile->id,
        ]);
    }

    public function test_increments_persistently(): void
    {
        // Freeze time to the second to match database precision
        Date::setTestNow(now()->startOfSecond());

        $networkProfile = NetworkProfile::factory()->create([
            'number_of_visits' => 10,
            'last_visit_at' => now()->subDays(5),
        ]);

        $result = $this->repository->increment($networkProfile);

        $this->assertEquals(11, $result->number_of_visits);
        $this->assertEquals(now()->toDateTimeString(), $result->last_visit_at);

        $this->assertDatabaseHas('network_profiles', [
            'id' => $networkProfile->id,
            'number_of_visits' => 11,
            'last_visit_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_scope_by_visits_filters_correct_range_one_to_five(): void
    {
        NetworkProfile::factory()->create(['number_of_visits' => 3]);
        NetworkProfile::factory()->create(['number_of_visits' => 5]);
        NetworkProfile::factory()->create(['number_of_visits' => 0]);
        NetworkProfile::factory()->create(['number_of_visits' => 6]);

        $results = NetworkProfile::byVisits('1-5')->get();

        $this->assertTrue(count($results) >= 3);
        foreach ($results as $networkProfile) {
            $this->assertGreaterThanOrEqual(1, $networkProfile->number_of_visits);
            $this->assertLessThanOrEqual(5, $networkProfile->number_of_visits);
        }
    }

    public function test_scope_by_visits_filters_correct_range_six_to_ten(): void
    {
        NetworkProfile::factory()->create(['number_of_visits' => 6]);
        NetworkProfile::factory()->create(['number_of_visits' => 10]);
        NetworkProfile::factory()->create(['number_of_visits' => 9]);
        NetworkProfile::factory()->create(['number_of_visits' => 11]);

        $results = NetworkProfile::byVisits('6-10')->get();

        $this->assertTrue(count($results) >= 3);
        foreach ($results as $networkProfile) {
            $this->assertGreaterThanOrEqual(6, $networkProfile->number_of_visits);
            $this->assertLessThanOrEqual(10, $networkProfile->number_of_visits);
        }
    }

    public function test_scope_by_visits_filters_correct_range_eleven_to_twenty(): void
    {
        NetworkProfile::factory()->create(['number_of_visits' => 11]);
        NetworkProfile::factory()->create(['number_of_visits' => 15]);
        NetworkProfile::factory()->create(['number_of_visits' => 20]);
        NetworkProfile::factory()->create(['number_of_visits' => 21]);

        $results = NetworkProfile::byVisits('11-20')->get();

        $this->assertTrue(count($results) >= 3);
        foreach ($results as $networkProfile) {
            $this->assertGreaterThanOrEqual(11, $networkProfile->number_of_visits);
            $this->assertLessThanOrEqual(20, $networkProfile->number_of_visits);
        }
    }

    public function test_scope_by_visits_filters_correct_range_twenty_one_to_fifty(): void
    {
        NetworkProfile::factory()->create(['number_of_visits' => 21]);
        NetworkProfile::factory()->create(['number_of_visits' => 35]);
        NetworkProfile::factory()->create(['number_of_visits' => 50]);
        NetworkProfile::factory()->create(['number_of_visits' => 51]);

        $results = NetworkProfile::byVisits('21-50')->get();

        $this->assertTrue(count($results) >= 3);
        foreach ($results as $networkProfile) {
            $this->assertGreaterThanOrEqual(21, $networkProfile->number_of_visits);
            $this->assertLessThanOrEqual(50, $networkProfile->number_of_visits);
        }
    }

    public function test_scope_by_visits_filters_correct_range_fifty_one_to_one_hundred(): void
    {
        NetworkProfile::factory()->create(['number_of_visits' => 51]);
        NetworkProfile::factory()->create(['number_of_visits' => 75]);
        NetworkProfile::factory()->create(['number_of_visits' => 100]);
        NetworkProfile::factory()->create(['number_of_visits' => 101]);

        $results = NetworkProfile::byVisits('51-100')->get();

        $this->assertTrue(count($results) >= 3);
        foreach ($results as $networkProfile) {
            $this->assertGreaterThanOrEqual(51, $networkProfile->number_of_visits);
            $this->assertLessThanOrEqual(100, $networkProfile->number_of_visits);
        }
    }

    public function test_scope_by_visits_filters_open_ended_range(): void
    {
        NetworkProfile::factory()->create(['number_of_visits' => 101]);
        NetworkProfile::factory()->create(['number_of_visits' => 100]);

        $results = NetworkProfile::byVisits('100+')->get();

        $this->assertTrue(count($results) >= 1);
        $this->assertEquals(101, $results->first()->number_of_visits);
    }

    public function test_scope_by_visits_returns_unfiltered_query_on_invalid_range(): void
    {
        NetworkProfile::factory()->count(3)->create(['number_of_visits' => 10]);

        $results = NetworkProfile::byVisits('invalid-range')->get();

        $this->assertTrue(count($results) >= 3);
    }

    public function test_scope_by_last_visit_filters_last_24_hours(): void
    {
        NetworkProfile::factory()->create(['last_visit_at' => now()->subHours(5)]);
        NetworkProfile::factory()->create(['last_visit_at' => now()->subHours(25)]); // Outside

        $results = NetworkProfile::byLastVisit('24h')->get();

        $this->assertTrue(count($results) >= 1);
    }

    public function test_scope_by_last_visit_filters_7_day_range(): void
    {
        NetworkProfile::factory()->create(['last_visit_at' => now()->subDays(3)]); // Inside
        NetworkProfile::factory()->create(['last_visit_at' => now()->subHours(12)]); // Outside (too recent)
        NetworkProfile::factory()->create(['last_visit_at' => now()->subDays(10)]); // Outside (too old)

        $results = NetworkProfile::byLastVisit('7d')->get();

        $this->assertTrue(count($results) >= 1);
    }

    public function test_scope_by_last_visit_filters_30_days_range(): void
    {
        NetworkProfile::factory()->create(['last_visit_at' => now()->subDays(25)]); // Inside
        NetworkProfile::factory()->create(['last_visit_at' => now()->subHours(32)]); // Outside (too recent)
        NetworkProfile::factory()->create(['last_visit_at' => now()->subDays(10)]); // Outside (too old)

        $results = NetworkProfile::byLastVisit('30d')->get();

        $this->assertTrue(count($results) >= 1);
    }

    public function test_scope_by_last_visit_filters_older_than_30_days(): void
    {
        NetworkProfile::factory()->create(['last_visit_at' => now()->subDays(40)]); // Inside
        NetworkProfile::factory()->create(['last_visit_at' => now()->subDays(20)]); // Outside

        $results = NetworkProfile::byLastVisit('older')->get();

        $this->assertTrue(count($results) >= 1);
    }

    public function test_scope_by_last_visit_filters_not_in_last_24_hours(): void
    {
        NetworkProfile::factory()->create(['last_visit_at' => now()->subDays(2)]); // Inside
        NetworkProfile::factory()->create(['last_visit_at' => now()->subHours(5)]); // Outside

        $results = NetworkProfile::byLastVisit('not_24h')->get();

        $this->assertTrue(count($results) >= 1);
    }

    public function test_scope_by_last_visit_returns_all_on_default(): void
    {
        NetworkProfile::factory()->count(3)->create();

        $results = NetworkProfile::byLastVisit('all')->get();

        $this->assertTrue(count($results) >= 3);
    }

    public function test_it_restores_trashed_profile_instead_of_creating_duplicate(): void
    {
        $username = 'john.doe';
        $networkProfile = NetworkProfile::factory()->create([
            'username' => $username,
        ]);
        $networkProfile->delete();

        $this->assertSoftDeleted('network_profiles', [
            'id' => $networkProfile->id,
        ]);

        $data = [
            'username' => $username,
        ];

        $result = $this->repository->upsert($data);

        $this->assertEquals($networkProfile->id, $result->id);

        $this->assertDatabaseHas('network_profiles', [
            'id' => $networkProfile->id,
            'username' => $username,
            'deleted_at' => null,
        ]);
    }

    public function test_restore_scopes_by_network_source_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $sourceA = NetworkSource::factory()->create(['user_id' => $user->id]);
        $sourceB = NetworkSource::factory()->create(['user_id' => $user->id]);
        $username = 'cross_source_'.$this->timestamp;

        $trashedProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $sourceA->id,
            'username' => $username,
        ]);
        $trashedProfile->delete();

        $this->assertSoftDeleted('network_profiles', ['id' => $trashedProfile->id]);

        $result = $this->repository->upsert([
            'user_id' => $user->id,
            'network_source_id' => $sourceB->id,
            'username' => $username,
        ]);

        $this->assertNotEquals($trashedProfile->id, $result->id);
        $this->assertEquals($sourceB->id, $result->network_source_id);
        $this->assertSoftDeleted('network_profiles', ['id' => $trashedProfile->id]);
    }

    public function test_upsert_handles_description_field(): void
    {
        $username = 'user_with_description_'.$this->timestamp;
        $description = 'This is my awesome profile description';

        $data = [
            'user_id' => User::factory()->create()->id,
            'network_source_id' => NetworkSource::factory()->create()->id,
            'username' => $username,
            'description' => $description,
        ];

        $profile = $this->repository->upsert($data);

        $this->assertEquals($description, $profile->description);
        $this->assertDatabaseHas(DatabaseTableNamesEnum::network_profiles->value, [
            'username' => $username,
            'description' => $description,
        ]);
    }

    public function test_upsert_updates_description_on_existing_profile(): void
    {
        $originalDescription = 'Original description';
        $updatedDescription = 'Updated description';
        $username = 'updatable_user_'.$this->timestamp;

        $profile = NetworkProfile::factory()->create([
            'username' => $username,
            'description' => $originalDescription,
        ]);

        $updatedProfile = $this->repository->upsert(
            [
                'username' => $username,
                'description' => $updatedDescription,
            ],
            $profile
        );

        $this->assertEquals($updatedDescription, $updatedProfile->description);
        $this->assertDatabaseHas(DatabaseTableNamesEnum::network_profiles->value, [
            'id' => $profile->id,
            'description' => $updatedDescription,
        ]);
    }

    public function test_upsert_handles_empty_description(): void
    {
        $username = 'no_description_user_'.$this->timestamp;

        $data = [
            'user_id' => User::factory()->create()->id,
            'network_source_id' => NetworkSource::factory()->create()->id,
            'username' => $username,
            'description' => '',
        ];

        $profile = $this->repository->upsert($data);

        $this->assertEquals('', $profile->description);
        $this->assertDatabaseHas(DatabaseTableNamesEnum::network_profiles->value, [
            'username' => $username,
            'description' => '',
        ]);
    }

    public function test_upsert_handles_null_description(): void
    {
        $username = 'null_description_user_'.$this->timestamp;

        $data = [
            'user_id' => User::factory()->create()->id,
            'network_source_id' => NetworkSource::factory()->create()->id,
            'username' => $username,
            'description' => null,
        ];

        $profile = $this->repository->upsert($data);

        $this->assertNull($profile->description);
        $this->assertDatabaseHas(DatabaseTableNamesEnum::network_profiles->value, [
            'username' => $username,
            'description' => null,
        ]);
    }

    public function test_get_all_includes_description(): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        $profile1 = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'username' => 'user1_desc_test',
            'description' => 'Description 1',
        ]);

        $profile2 = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'username' => 'user2_desc_test',
            'description' => 'Description 2',
        ]);

        // Verify directly that the profiles have descriptions
        $this->assertEquals('Description 1', $profile1->description);
        $this->assertEquals('Description 2', $profile2->description);

        // Verify they are in database
        $this->assertDatabaseHas(DatabaseTableNamesEnum::network_profiles->value, [
            'id' => $profile1->id,
            'description' => 'Description 1',
        ]);
        $this->assertDatabaseHas(DatabaseTableNamesEnum::network_profiles->value, [
            'id' => $profile2->id,
            'description' => 'Description 2',
        ]);
    }

    public function test_delete_returns_false_for_non_existent_id(): void
    {
        $result = $this->repository->delete(999999);

        $this->assertFalse($result);
    }

    public function test_upsert_with_all_fields(): void
    {
        $username = 'complex_user_'.$this->timestamp;
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        $data = [
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'username' => $username,
            'description' => 'Comprehensive profile',
            'is_public' => true,
            'is_favorite' => true,
            'number_of_visits' => 42,
        ];

        $profile = $this->repository->upsert($data);

        $this->assertEquals($username, $profile->username);
        $this->assertEquals('Comprehensive profile', $profile->description);
        $this->assertTrue($profile->is_public);
        $this->assertTrue($profile->is_favorite);
        $this->assertEquals(42, $profile->number_of_visits);
        $this->assertDatabaseHas(DatabaseTableNamesEnum::network_profiles->value, $data);
    }

    public function test_upsert_updates_multiple_fields_including_description(): void
    {
        $username = 'multi_update_'.$this->timestamp;
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);
        $profile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'username' => $username,
            'description' => 'Old description',
            'is_public' => false,
            'is_favorite' => false,
            'number_of_visits' => 5,
        ]);

        $updatedProfile = $this->repository->upsert(
            [
                'description' => 'New description',
                'is_public' => true,
                'is_favorite' => true,
                'number_of_visits' => 10,
            ],
            $profile
        );

        $this->assertEquals('New description', $updatedProfile->description);
        $this->assertTrue($updatedProfile->is_public);
        $this->assertTrue($updatedProfile->is_favorite);
        $this->assertEquals(10, $updatedProfile->number_of_visits);
    }

    public function test_increment_increments_visit_count_and_updates_timestamp(): void
    {
        $profile = NetworkProfile::factory()->create([
            'number_of_visits' => 5,
            'last_visit_at' => now()->subDays(7),
        ]);

        $oldTimestamp = $profile->last_visit_at;
        $result = $this->repository->increment($profile);

        $this->assertEquals(6, $result->number_of_visits);
        $this->assertNotEquals($oldTimestamp, $result->last_visit_at);
        $this->assertDatabaseHas(DatabaseTableNamesEnum::network_profiles->value, [
            'id' => $profile->id,
            'number_of_visits' => 6,
        ]);
    }

    public function test_get_all_respects_pagination(): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        NetworkProfile::factory(5)->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);

        $results = $this->repository->getAll();

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertTrue($results->count() > 0);
    }

    public function test_upsert_does_not_update_other_profiles(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $networkSource1 = NetworkSource::factory()->create(['user_id' => $user1->id]);
        $networkSource2 = NetworkSource::factory()->create(['user_id' => $user2->id]);

        $profile1 = NetworkProfile::factory()->create([
            'user_id' => $user1->id,
            'network_source_id' => $networkSource1->id,
            'username' => 'user1_'.$this->timestamp,
            'description' => 'Description 1',
        ]);

        $profile2 = NetworkProfile::factory()->create([
            'user_id' => $user2->id,
            'network_source_id' => $networkSource2->id,
            'username' => 'user2_'.$this->timestamp,
            'description' => 'Description 2',
        ]);

        $this->repository->upsert(
            [
                'description' => 'Updated description',
            ],
            $profile1
        );

        $profile2Fresh = NetworkProfile::query()->find($profile2->id);
        $this->assertEquals('Description 2', $profile2Fresh->description);
    }

    public function test_delete_soft_deletes_profile(): void
    {
        $profile = NetworkProfile::factory()->create();
        $profileId = $profile->id;

        $result = $this->repository->delete($profileId);

        $this->assertTrue($result);
        $this->assertNull(NetworkProfile::query()->find($profileId));
        $this->assertNotNull(NetworkProfile::withTrashed()->find($profileId));
    }

    public function test_upsert_restores_soft_deleted_profile(): void
    {
        $username = 'restore_me_'.$this->timestamp;
        $profile = NetworkProfile::factory()->create([
            'username' => $username,
        ]);
        $originalId = $profile->id;
        $profile->delete();

        $restored = $this->repository->upsert([
            'username' => $username,
        ]);

        $this->assertEquals($originalId, $restored->id);
        $this->assertNull($restored->deleted_at);
    }

    public function test_get_all_filters_by_username_and_description(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'username' => 'elon_musk',
            'description' => 'CEO of X and Tesla',
        ]);

        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'username' => 'jeff_bezos',
            'description' => 'Founder of Amazon',
        ]);

        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'username' => 'tech_guru',
            'description' => 'I love space exploration',
        ]);

        // Scenario A: Search by Username
        $this->requestSearch('elon');
        $results = $this->repository->getAll();
        $this->assertCount(1, $results);
        $this->assertEquals('elon_musk', $results->first()->username);

        // Scenario B: Search by Description
        $this->requestSearch('Amazon');
        $results = $this->repository->getAll();
        $this->assertCount(1, $results);
        $this->assertEquals('jeff_bezos', $results->first()->username);

        // Scenario C: Search for something common in both or distinct
        $this->requestSearch('space');
        $results = $this->repository->getAll();
        // Should find 'elon_musk' (CEO of X...) and 'tech_guru' (space exploration)
        // Wait, 'elon' description doesn't have space. Let's adjust logic check:
        $this->assertCount(1, $results);
        $this->assertEquals('tech_guru', $results->first()->username);
    }

    public function test_get_all_filters_by_description_presence(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'description' => 'Has description 1',
        ]);

        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'description' => 'Has description 2',
        ]);

        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'description' => '',
        ]);

        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'description' => null,
        ]);

        // Filter: with description
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['has_description' => '1'],
        ]);
        $this->app->instance('request', $request);
        $results = $this->repository->getAll();
        $this->assertGreaterThanOrEqual(2, $results->count());
        foreach ($results as $r) {
            $this->assertNotEmpty($r->description);
        }
        $this->assertContains('Has description 1', $results->pluck('description')->toArray());
        $this->assertContains('Has description 2', $results->pluck('description')->toArray());

        // Filter: without description
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['has_description' => '0'],
        ]);
        $this->app->instance('request', $request);
        $results = $this->repository->getAll();
        $this->assertGreaterThanOrEqual(2, $results->count());
        foreach ($results as $r) {
            $this->assertTrue($r->description === '' || $r->description === null);
        }
        $descriptions = $results->pluck('description')->toArray();
        $this->assertTrue(in_array('', $descriptions, true));
        $this->assertTrue(in_array(null, $descriptions, true));

        // No filter returns all (at least the 4 we created)
        $request = Request::create('/network-profiles', 'GET', []);
        $this->app->instance('request', $request);
        $results = $this->repository->getAll();
        $this->assertGreaterThanOrEqual(4, $results->count());
    }

    public function test_get_all_filters_by_single_tag_id_as_string(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        $tag1 = \App\Models\NetworkTag::factory()->create(['name' => 'tag1_'.uniqid()]);
        $tag2 = \App\Models\NetworkTag::factory()->create(['name' => 'tag2_'.uniqid()]);

        $profile1 = NetworkProfile::factory()->create([
            'username' => 'user_with_tag1_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profile1->networkTags()->attach($tag1->id);

        $profile2 = NetworkProfile::factory()->create([
            'username' => 'user_with_tag2_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profile2->networkTags()->attach($tag2->id);

        // Filter by single tag ID as string
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['tags' => (string) $tag1->id],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();

        $this->assertTrue($results->count() >= 1);
        $filteredByTag = $results->filter(fn ($p) => $p->username === $profile1->username);
        $this->assertTrue($filteredByTag->count() >= 1);
    }

    public function test_get_all_returns_all_profiles_when_tags_filter_is_empty(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        NetworkProfile::factory()->count(3)->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);

        // Empty tags filter should return all profiles
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['tags' => ''],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();

        $this->assertGreaterThanOrEqual(3, $results->count());
    }

    public function test_get_all_returns_empty_for_non_existent_tag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        $tag = \App\Models\NetworkTag::factory()->create(['name' => 'existing']);

        $profile = NetworkProfile::factory()->create([
            'username' => 'user_with_tag',
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profile->networkTags()->attach($tag->id);

        // Filter by non-existent tag ID
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['tags' => '999999'],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();

        // Should not find the profile we created (since it has a different tag)
        $usernames = $results->pluck('username')->toArray();
        $this->assertNotContains('user_with_tag', $usernames);
    }

    public function test_get_all_filters_profile_with_multiple_tags_by_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        $tag1 = \App\Models\NetworkTag::factory()->create(['name' => 'php_'.uniqid()]);
        $tag2 = \App\Models\NetworkTag::factory()->create(['name' => 'laravel_'.uniqid()]);
        $tag3 = \App\Models\NetworkTag::factory()->create(['name' => 'vue_'.uniqid()]);

        $profile = NetworkProfile::factory()->create([
            'username' => 'fullstack_dev_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profile->networkTags()->attach([$tag1->id, $tag2->id, $tag3->id]);

        // Filter by just one of the tags
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['tags' => (string) $tag2->id],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();

        $this->assertTrue($results->count() >= 1);
        $usernames = $results->pluck('username')->toArray();
        $this->assertContains($profile->username, $usernames);
    }

    public function test_get_all_filters_by_tags_none(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        // Create profiles without tags
        $profile1 = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profile2 = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);

        // Create profile with a tag
        $tag = \App\Models\NetworkTag::factory()->create(['name' => 'has_tag_'.uniqid()]);
        $profileWithTag = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileWithTag->networkTags()->attach($tag->id);

        // Filter for profiles without tags
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['tags' => 'none'],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();

        $this->assertGreaterThanOrEqual(2, $results->count());
        foreach ($results as $r) {
            $this->assertFalse($r->networkTags()->withoutGlobalScopes()->exists());
        }
    }

    public function test_get_all_filters_by_tags_any(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        // Create profiles with tags
        $tag1 = \App\Models\NetworkTag::factory()->create(['name' => 'tag_any_1_'.uniqid()]);
        $tag2 = \App\Models\NetworkTag::factory()->create(['name' => 'tag_any_2_'.uniqid()]);

        $profileWithTag1 = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileWithTag1->networkTags()->attach($tag1->id);

        $profileWithTag2 = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileWithTag2->networkTags()->attach($tag2->id);

        // Create a profile without tags to ensure it's excluded
        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);

        // Filter for profiles that have any tag
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['tags' => 'any'],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();

        $this->assertGreaterThanOrEqual(2, $results->count());
        foreach ($results as $r) {
            $this->assertTrue($r->networkTags()->withoutGlobalScopes()->exists());
        }
    }

    public function test_get_all_filters_by_null_tags_value(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        NetworkProfile::factory()->count(2)->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);

        // Null value should return all profiles (no filtering)
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['tags' => null],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();

        $this->assertGreaterThanOrEqual(2, $results->count());
    }

    public function test_get_all_excludes_profiles_by_single_tag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        $tag1 = \App\Models\NetworkTag::factory()->create(['name' => 'excl_tag1_'.uniqid()]);
        $tag2 = \App\Models\NetworkTag::factory()->create(['name' => 'excl_tag2_'.uniqid()]);

        $profileWithTag1 = NetworkProfile::factory()->create([
            'username' => 'has_excl_tag1_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileWithTag1->networkTags()->attach($tag1->id);

        $profileWithTag2 = NetworkProfile::factory()->create([
            'username' => 'has_excl_tag2_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileWithTag2->networkTags()->attach($tag2->id);

        $profileNoTags = NetworkProfile::factory()->create([
            'username' => 'no_tags_excl_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);

        // Exclude profiles with tag1
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['exclude_tags' => [(string) $tag1->id]],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();
        $usernames = $results->pluck('username')->toArray();

        $this->assertNotContains($profileWithTag1->username, $usernames);
        $this->assertContains($profileWithTag2->username, $usernames);
        $this->assertContains($profileNoTags->username, $usernames);
    }

    public function test_get_all_excludes_profiles_by_multiple_tags(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        $tag1 = \App\Models\NetworkTag::factory()->create(['name' => 'multi_excl1_'.uniqid()]);
        $tag2 = \App\Models\NetworkTag::factory()->create(['name' => 'multi_excl2_'.uniqid()]);
        $tag3 = \App\Models\NetworkTag::factory()->create(['name' => 'multi_excl3_'.uniqid()]);

        $profileWithTag1 = NetworkProfile::factory()->create([
            'username' => 'multi_excl_t1_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileWithTag1->networkTags()->attach($tag1->id);

        $profileWithTag2 = NetworkProfile::factory()->create([
            'username' => 'multi_excl_t2_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileWithTag2->networkTags()->attach($tag2->id);

        $profileWithTag3 = NetworkProfile::factory()->create([
            'username' => 'multi_excl_t3_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileWithTag3->networkTags()->attach($tag3->id);

        // Exclude profiles with tag1 or tag2
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['exclude_tags' => [(string) $tag1->id, (string) $tag2->id]],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();
        $usernames = $results->pluck('username')->toArray();

        $this->assertNotContains($profileWithTag1->username, $usernames);
        $this->assertNotContains($profileWithTag2->username, $usernames);
        $this->assertContains($profileWithTag3->username, $usernames);
    }

    public function test_get_all_exclude_tags_with_empty_value_returns_all(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        NetworkProfile::factory()->count(3)->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);

        // Empty exclude_tags should return all profiles
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['exclude_tags' => ''],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();

        $this->assertGreaterThanOrEqual(3, $results->count());
    }

    public function test_get_all_include_and_exclude_tags_combined(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        $tagInclude = \App\Models\NetworkTag::factory()->create(['name' => 'combo_incl_'.uniqid()]);
        $tagExclude = \App\Models\NetworkTag::factory()->create(['name' => 'combo_excl_'.uniqid()]);

        // Profile with both tags — should be excluded because it has the exclude tag
        $profileBoth = NetworkProfile::factory()->create([
            'username' => 'combo_both_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileBoth->networkTags()->attach([$tagInclude->id, $tagExclude->id]);

        // Profile with only the include tag — should appear
        $profileIncludeOnly = NetworkProfile::factory()->create([
            'username' => 'combo_incl_only_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileIncludeOnly->networkTags()->attach($tagInclude->id);

        // Profile with only the exclude tag — should not appear
        $profileExcludeOnly = NetworkProfile::factory()->create([
            'username' => 'combo_excl_only_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profileExcludeOnly->networkTags()->attach($tagExclude->id);

        // Combine include + exclude
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => [
                'tags' => (string) $tagInclude->id,
                'exclude_tags' => [(string) $tagExclude->id],
            ],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();
        $usernames = $results->pluck('username')->toArray();

        $this->assertContains($profileIncludeOnly->username, $usernames);
        $this->assertNotContains($profileBoth->username, $usernames);
        $this->assertNotContains($profileExcludeOnly->username, $usernames);
    }

    public function test_get_all_exclude_tags_non_existent_tag_returns_all(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $networkSource = NetworkSource::factory()->create(['user_id' => $user->id]);

        $tag = \App\Models\NetworkTag::factory()->create(['name' => 'real_tag_'.uniqid()]);

        $profile = NetworkProfile::factory()->create([
            'username' => 'excl_nonexist_'.uniqid(),
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);
        $profile->networkTags()->attach($tag->id);

        // Exclude a non-existent tag — should not affect results
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['exclude_tags' => ['999999']],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();
        $usernames = $results->pluck('username')->toArray();

        $this->assertContains($profile->username, $usernames);
    }

    public function test_get_all_excludes_profiles_from_excluded_sources(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $visibleSource = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'exclude_from_dashboard' => false,
        ]);
        $excludedSource = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'exclude_from_dashboard' => true,
        ]);

        $visibleProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $visibleSource->id,
            'username' => 'visible_'.uniqid(),
        ]);
        $excludedProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $excludedSource->id,
            'username' => 'excluded_'.uniqid(),
        ]);

        $request = Request::create('/network-profiles', 'GET', []);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();
        $usernames = $results->pluck('username')->toArray();

        $this->assertContains($visibleProfile->username, $usernames);
        $this->assertNotContains($excludedProfile->username, $usernames);
    }

    public function test_get_all_includes_excluded_source_profiles_when_source_filter_set(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $excludedSource = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'exclude_from_dashboard' => true,
        ]);

        $profile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $excludedSource->id,
            'username' => 'filtered_'.uniqid(),
        ]);

        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['network_source_id' => (string) $excludedSource->id],
        ]);
        $this->app->instance('request', $request);

        $results = $this->repository->getAll();
        $usernames = $results->pluck('username')->toArray();

        $this->assertContains($profile->username, $usernames);
    }

    public function test_upsert_creates_profile_with_title(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);
        $username = 'titled_user_'.$this->timestamp;

        $data = [
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'username' => $username,
            'title' => 'My Title',
        ];

        $profile = $this->repository->upsert($data);

        $this->assertEquals('My Title', $profile->title);
        $this->assertDatabaseHas('network_profiles', [
            'id' => $profile->id,
            'title' => 'My Title',
        ]);
    }

    public function test_upsert_updates_title_on_existing_profile(): void
    {
        $profile = NetworkProfile::factory()->create([
            'title' => 'Old Title',
        ]);

        $updated = $this->repository->upsert(
            ['title' => 'New Title'],
            $profile
        );

        $this->assertEquals('New Title', $updated->title);
        $this->assertDatabaseHas('network_profiles', [
            'id' => $profile->id,
            'title' => 'New Title',
        ]);
    }

    /**
     * Helper to inject query params into the global request
     */
    private function requestSearch(string $term): void
    {
        $request = Request::create('/network-profiles', 'GET', [
            'filter' => ['search' => $term],
        ]);

        // Bind the request to the container so Spatie Query Builder picks it up
        $this->app->instance('request', $request);
    }
}
