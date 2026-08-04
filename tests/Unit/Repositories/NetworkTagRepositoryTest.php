<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\NetworkTag\NetworkTagDuplicationException;
use App\Models\NetworkProfile;
use App\Models\NetworkTag;
use App\Models\User;
use App\Repositories\NetworkTagRepository;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class NetworkTagRepositoryTest extends TestCase
{
    private NetworkTagRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->repository = new NetworkTagRepository;
    }

    #[Override]
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_get_all_returns_paginated_results(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        NetworkTag::factory()->count(2)->create(['user_id' => $user->id]);

        $results = $this->repository->getAll(paginate: true);

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertGreaterThanOrEqual(2, $results->total());
    }

    public function test_get_all_returns_unpaginated_results(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        NetworkTag::factory()->count(2)->create(['user_id' => $user->id]);

        $results = $this->repository->getAll(paginate: false);

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertGreaterThanOrEqual(2, $results->total());
    }

    public function test_upsert_creates_new_record(): void
    {
        $user = User::factory()->create();
        $data = [
            'user_id' => $user->id,
            'name' => 'Tag_'.uniqid(),
            'description' => 'Description_'.uniqid(),
        ];

        $result = $this->repository->upsert($data);

        $this->assertInstanceOf(NetworkTag::class, $result);
        $this->assertDatabaseHas('network_tags', $data);
    }

    public function test_upsert_updates_existing_record(): void
    {
        $user = User::factory()->create();
        $tag = NetworkTag::factory()->create(['user_id' => $user->id, 'name' => 'Original']);
        $data = ['name' => 'Updated', 'user_id' => $user->id, 'description' => 'Updated description'];

        $result = $this->repository->upsert($data, $tag);

        $this->assertSame('Updated', $result->name);
        $this->assertSame($tag->id, $result->id);
    }

    public function test_upsert_restores_soft_deleted_record(): void
    {
        $user = User::factory()->create();
        $unique = uniqid();
        $name = 'Tag_'.$unique;

        $trashed = NetworkTag::factory()->create([
            'user_id' => $user->id,
            'name' => $name,
            'deleted_at' => now(),
        ]);

        $data = ['user_id' => $user->id, 'name' => $name, 'description' => 'restored'];
        $result = $this->repository->upsert($data);

        $this->assertSame($trashed->id, $result->id);
        $this->assertNull($result->deleted_at);
        $this->assertSame('restored', $result->description);
    }

    public function test_upsert_restore_detaches_old_pivot_relationships(): void
    {
        $user = User::factory()->create();
        $unique = uniqid();
        $name = 'Tag_'.$unique;

        $trashed = NetworkTag::factory()->create([
            'user_id' => $user->id,
            'name' => $name,
            'deleted_at' => now(),
        ]);

        $profile = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $trashed->networkProfiles()->attach($profile->id);

        $this->assertDatabaseHas('network_profile_network_tag', [
            'network_tag_id' => $trashed->id,
            'network_profile_id' => $profile->id,
        ]);

        $data = ['user_id' => $user->id, 'name' => $name, 'description' => 'restored'];
        $result = $this->repository->upsert($data);

        $this->assertSame($trashed->id, $result->id);
        $this->assertDatabaseMissing('network_profile_network_tag', [
            'network_tag_id' => $trashed->id,
            'network_profile_id' => $profile->id,
        ]);
    }

    public function test_upsert_throws_duplication_exception(): void
    {
        $user = User::factory()->create();
        $unique = uniqid();
        $data = [
            'user_id' => $user->id,
            'name' => 'Duplicate_'.$unique,
            'description' => 'dup',
        ];

        NetworkTag::factory()->create($data);

        $this->expectException(NetworkTagDuplicationException::class);

        $this->repository->upsert($data);
    }

    public function test_upsert_rethrows_generic_exception_during_update(): void
    {
        $mock = $this->getMockBuilder(NetworkTag::class)
            ->onlyMethods(['update'])
            ->getMock();

        $mock->method('update')
            ->willThrowException(new Exception('Generic Error', 500));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Generic Error');

        $this->repository->upsert(['name' => 'New Name', 'description' => 'x'], $mock);
    }

    public function test_delete_returns_true_on_success(): void
    {
        $user = User::factory()->create();
        $tag = NetworkTag::factory()->create(['user_id' => $user->id]);

        $result = $this->repository->delete($tag->id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('network_tags', ['id' => $tag->id]);
    }

    public function test_delete_returns_false_on_failure(): void
    {
        $result = $this->repository->delete(0);

        $this->assertFalse($result);
    }

    public function test_get_all_without_with_count_does_not_include_network_profiles_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tag = NetworkTag::factory()->create(['user_id' => $user->id]);
        $profile = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $tag->networkProfiles()->attach($profile->id);

        $results = $this->repository->getAll(paginate: false, defaultSort: 'name', withCount: false);

        $first = $results->first();
        $this->assertNotNull($first);
        $this->assertArrayNotHasKey('network_profiles_count', $first->getAttributes());
    }

    public function test_get_all_with_with_count_includes_network_profiles_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tag = NetworkTag::factory()->create(['user_id' => $user->id]);
        $profile1 = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $profile2 = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $tag->networkProfiles()->attach([$profile1->id, $profile2->id]);

        $results = $this->repository->getAll(paginate: false, defaultSort: 'name', withCount: true);

        $first = $results->first();
        $this->assertNotNull($first);
        $this->assertArrayHasKey('network_profiles_count', $first->getAttributes());
    }

    public function test_get_all_search_filter_matches_the_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $matching = $this->createTag($user, 'Matching_'.$unique, 'Description');
        $other = $this->createTag($user, 'Other_'.$unique, 'Description');

        $this->requestSearch($matching->name);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($matching->name, $names);
        $this->assertNotContains($other->name, $names);
    }

    public function test_get_all_search_filter_matches_the_description(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $matching = $this->createTag($user, 'Tag_'.$unique, 'Describes_'.$unique);
        $other = $this->createTag($user, 'Other_'.$unique, 'Unrelated');

        $this->requestSearch('Describes_'.$unique);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($matching->name, $names);
        $this->assertNotContains($other->name, $names);
    }

    public function test_get_all_search_filter_treats_wildcards_literally(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $literal = $this->createTag($user, '100%_'.$unique, 'Description');
        $other = $this->createTag($user, 'Other_'.$unique, 'Description');

        $this->requestSearch('100%_'.$unique);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($literal->name, $names);
        $this->assertNotContains($other->name, $names);
    }

    public function test_get_all_search_filter_escapes_the_underscore_wildcard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $literal = $this->createTag($user, 'A_b'.$unique, 'Description');
        $wildcard = $this->createTag($user, 'AXb'.$unique, 'Description');

        $this->requestSearch('A_b'.$unique);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($literal->name, $names);
        // Unescaped, `_` matches any single character and would pull AXb in too.
        $this->assertNotContains($wildcard->name, $names);
    }

    public function test_get_all_search_filter_handles_a_comma_in_the_term(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $match = $this->createTag($user, 'Alpha,Beta'.$unique, 'Description');
        $other = $this->createTag($user, 'Gamma'.$unique, 'Description');

        // Spatie explodes comma-separated filter values into an array.
        $this->requestSearch('Alpha,Beta'.$unique);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($match->name, $names);
        $this->assertNotContains($other->name, $names);
    }

    public function test_get_all_search_filter_does_not_leak_another_users_tag(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $unique = uniqid();
        $foreign = $this->createTag($otherUser, 'Shared'.$unique.'_theirs', 'Description');
        $this->actingAs($user);
        $own = $this->createTag($user, 'Shared'.$unique.'_mine', 'Description');

        $this->requestSearch('Shared'.$unique);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($own->name, $names);
        $this->assertNotContains($foreign->name, $names);
    }

    public function test_get_all_sorts_descending_by_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $first = $this->createTag($user, 'AAA_'.$unique, 'Description');
        $last = $this->createTag($user, 'ZZZ_'.$unique, 'Description');

        $this->requestQuery(['sort' => '-name']);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertLessThan(
            array_search($first->name, $names, true),
            array_search($last->name, $names, true)
        );
    }

    public function test_get_all_sorts_by_the_network_profiles_count_alias(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $empty = $this->createTag($user, 'Empty_'.$unique, 'Description');
        $busy = $this->createTag($user, 'Busy_'.$unique, 'Description');

        $profile = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $busy->networkProfiles()->attach($profile->id);

        $this->requestQuery(['sort' => '-network_profiles_count']);
        $names = $this->repository->getAll(withCount: true, filterable: true)->pluck('name')->toArray();

        $this->assertLessThan(
            array_search($empty->name, $names, true),
            array_search($busy->name, $names, true)
        );
    }

    public function test_get_all_profiles_filter_isolates_tags_without_profiles(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $empty = $this->createTag($user, 'Empty_'.$unique, 'Description');
        $busy = $this->createTag($user, 'Busy_'.$unique, 'Description');

        $profile = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $busy->networkProfiles()->attach($profile->id);

        $this->requestQuery(['filter' => ['profiles' => '0']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($empty->name, $names);
        $this->assertNotContains($busy->name, $names);
    }

    public function test_get_all_profiles_filter_matches_a_count_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $empty = $this->createTag($user, 'Empty_'.$unique, 'Description');
        $inRange = $this->createTag($user, 'InRange_'.$unique, 'Description');

        $profiles = NetworkProfile::factory()->count(3)->create(['user_id' => $user->id]);
        $inRange->networkProfiles()->attach($profiles->pluck('id')->toArray());

        $this->requestQuery(['filter' => ['profiles' => '1-5']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($inRange->name, $names);
        $this->assertNotContains($empty->name, $names);
    }

    public function test_get_all_profiles_filter_excludes_counts_above_the_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $tooMany = $this->createTag($user, 'TooMany_'.$unique, 'Description');

        $profiles = NetworkProfile::factory()->count(7)->create(['user_id' => $user->id]);
        $tooMany->networkProfiles()->attach($profiles->pluck('id')->toArray());

        $this->requestQuery(['filter' => ['profiles' => '1-5']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertNotContains($tooMany->name, $names);
    }

    public function test_get_all_profiles_filter_matches_the_open_ended_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $small = $this->createTag($user, 'Small_'.$unique, 'Description');

        $profile = NetworkProfile::factory()->create(['user_id' => $user->id]);
        $small->networkProfiles()->attach($profile->id);

        $this->requestQuery(['filter' => ['profiles' => '100+']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertNotContains($small->name, $names);
    }

    public function test_get_all_profiles_filter_matches_only_the_bucket_holding_the_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $tag = $this->createTag($user, 'Eight_'.$unique, 'Description');

        $profiles = NetworkProfile::factory()->count(8)->create(['user_id' => $user->id]);
        $tag->networkProfiles()->attach($profiles->pluck('id')->toArray());

        foreach (['0', '1-5', '6-10', '11-20', '21-50', '51-100', '100+'] as $range) {
            $this->requestQuery(['filter' => ['profiles' => $range]]);
            $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

            if ($range === '6-10') {
                $this->assertContains($tag->name, $names, "range {$range} should match 8 profiles");

                continue;
            }

            $this->assertNotContains($tag->name, $names, "range {$range} should not match 8 profiles");
        }
    }

    public function test_get_all_profiles_filter_ignores_an_unknown_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $tag = $this->createTag($user, 'Kept_'.$unique, 'Description');

        $this->requestQuery(['filter' => ['profiles' => 'bogus']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($tag->name, $names);
    }

    public function test_get_all_profiles_filter_does_not_count_another_users_profiles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $tag = $this->createTag($user, 'Shared_'.$unique, 'Description');

        $foreignProfile = NetworkProfile::factory()->create(['user_id' => $otherUser->id]);
        $tag->networkProfiles()->attach($foreignProfile->id);

        // Only the authenticated user's profiles count towards the range.
        $this->requestQuery(['filter' => ['profiles' => '0']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($tag->name, $names);
    }

    public function test_get_all_ignores_request_filters_and_sorts_when_not_filterable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $first = $this->createTag($user, 'AAA_'.$unique, 'Description');
        $last = $this->createTag($user, 'ZZZ_'.$unique, 'Description');

        // The dashboard reuses this repository for its tag dropdowns, where the
        // request carries profile filters and sorts that must not affect the
        // alphabetically ordered dropdowns or be rejected by Spatie.
        $this->requestQuery([
            'filter' => ['search' => 'no_such_tag_'.$unique],
            'sort' => '-last_visit_at',
        ]);
        $names = $this->repository->getAll()->pluck('name')->toArray();

        $this->assertSame([$first->name, $last->name], $names);
    }

    /**
     * Creates a network tag owned by the given user.
     */
    private function createTag(User $user, string $name, ?string $description): NetworkTag
    {
        return NetworkTag::query()->withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * Injects a search term into the global request.
     */
    private function requestSearch(string $term): void
    {
        $this->requestQuery(['filter' => ['search' => $term]]);
    }

    /**
     * Binds a request with the given query params so Spatie picks them up.
     */
    private function requestQuery(array $query): void
    {
        $this->app->instance(
            'request',
            Request::create('/network-tags', 'GET', $query)
        );
    }
}
