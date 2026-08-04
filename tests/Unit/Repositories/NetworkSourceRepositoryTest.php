<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\NetworkSource\NetworkSourceDuplicationException;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use App\Repositories\NetworkSourceRepository;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class NetworkSourceRepositoryTest extends TestCase
{
    private NetworkSourceRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->repository = new NetworkSourceRepository;
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
        NetworkSource::factory()->count(2)->create(['user_id' => $user->id]);

        $results = $this->repository->getAll(paginate: true);

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertGreaterThanOrEqual(2, $results->total());
    }

    public function test_get_all_returns_unpaginated_results(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        NetworkSource::factory()->count(2)->create(['user_id' => $user->id]);

        $results = $this->repository->getAll(paginate: false);

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertGreaterThanOrEqual(2, $results->total());
    }

    public function test_upsert_creates_new_record(): void
    {
        $user = User::factory()->create();
        $data = [
            'user_id' => $user->id,
            'name' => 'GitHub_'.uniqid(),
            'url' => 'https://github.com/'.uniqid(),
        ];

        $result = $this->repository->upsert($data);

        $this->assertInstanceOf(NetworkSource::class, $result);
        $this->assertDatabaseHas('network_sources', $data);
    }

    public function test_upsert_updates_existing_record(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id, 'name' => 'Original']);
        $data = ['name' => 'Updated', 'user_id' => $user->id];

        $result = $this->repository->upsert($data, $source);

        $this->assertSame('Updated', $result->name);
        $this->assertSame($source->id, $result->id);
    }

    public function test_upsert_restores_soft_deleted_record(): void
    {
        $user = User::factory()->create();
        $unique = uniqid();
        $name = 'LinkedIn_'.$unique;
        $url = 'https://linkedin.com/'.$unique;

        $trashed = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'name' => $name,
            'url' => $url,
            'deleted_at' => now(),
        ]);

        $data = ['user_id' => $user->id, 'name' => $name, 'url' => $url];
        $result = $this->repository->upsert($data);

        $this->assertSame($trashed->id, $result->id);
        $this->assertNull($result->deleted_at);
    }

    public function test_upsert_throws_duplication_exception(): void
    {
        $user = User::factory()->create();
        $unique = uniqid();
        $data = [
            'user_id' => $user->id,
            'name' => 'Duplicate_'.$unique,
            'url' => 'https://duplicate.com/'.$unique,
        ];

        NetworkSource::factory()->create($data);

        $this->expectException(NetworkSourceDuplicationException::class);

        $this->repository->upsert($data);
    }

    public function test_upsert_rethrows_generic_exception_during_update(): void
    {
        $mock = $this->getMockBuilder(NetworkSource::class)
            ->onlyMethods(['update'])
            ->getMock();

        $mock->method('update')
            ->willThrowException(new Exception('Generic Error', 500));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Generic Error');

        $this->repository->upsert(['name' => 'New Name'], $mock);
    }

    public function test_delete_returns_true_on_success(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);

        $result = $this->repository->delete($source->id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('network_sources', ['id' => $source->id]);
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
        NetworkSource::factory()->create(['user_id' => $user->id]);

        $results = $this->repository->getAll(paginate: false, defaultSort: 'name', withCount: false);

        $first = $results->first();
        $this->assertNotNull($first);
        $this->assertArrayNotHasKey('network_profiles_count', $first->getAttributes());
    }

    public function test_get_all_with_with_count_includes_network_profiles_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        NetworkSource::factory()->create(['user_id' => $user->id]);

        $results = $this->repository->getAll(paginate: false, defaultSort: 'name', withCount: true);

        $first = $results->first();
        $this->assertNotNull($first);
        $this->assertArrayHasKey('network_profiles_count', $first->getAttributes());
    }

    public function test_upsert_persists_icon_for_known_url(): void
    {
        $user = User::factory()->create();
        $unique = uniqid();
        $data = [
            'user_id' => $user->id,
            'name' => 'YouTube_'.$unique,
            'url' => 'https://youtube.com/@'.$unique,
        ];

        $result = $this->repository->upsert($data);

        $this->assertSame('youtube', $result->icon);
        $this->assertDatabaseHas('network_sources', [
            'id' => $result->id,
            'icon' => 'youtube',
        ]);
    }

    public function test_upsert_persists_null_icon_for_unknown_url(): void
    {
        $user = User::factory()->create();
        $unique = uniqid();
        $data = [
            'user_id' => $user->id,
            'name' => 'Unknown_'.$unique,
            'url' => 'https://example.com/'.$unique,
        ];

        $result = $this->repository->upsert($data);

        $this->assertNull($result->icon);
        $this->assertDatabaseHas('network_sources', [
            'id' => $result->id,
            'icon' => null,
        ]);
    }

    public function test_upsert_creates_source_with_exclude_from_dashboard(): void
    {
        $user = User::factory()->create();
        $unique = uniqid();
        $data = [
            'user_id' => $user->id,
            'name' => 'Source_'.$unique,
            'url' => 'https://example.com/'.$unique,
            'exclude_from_dashboard' => true,
        ];

        $result = $this->repository->upsert($data);

        $this->assertTrue($result->exclude_from_dashboard);
        $this->assertDatabaseHas('network_sources', [
            'id' => $result->id,
            'exclude_from_dashboard' => true,
        ]);
    }

    public function test_upsert_updates_exclude_from_dashboard(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'exclude_from_dashboard' => false,
        ]);

        $result = $this->repository->upsert(
            ['exclude_from_dashboard' => true],
            $source
        );

        $this->assertTrue($result->exclude_from_dashboard);
    }

    public function test_get_all_search_filter_matches_the_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $matching = $this->createSource($user, 'Matching_'.$unique, 'https://matching.test/'.$unique);
        $other = $this->createSource($user, 'Other_'.$unique, 'https://other.test/'.$unique);

        $this->requestSearch($matching->name);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($matching->name, $names);
        $this->assertNotContains($other->name, $names);
    }

    public function test_get_all_search_filter_matches_the_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $matching = $this->createSource($user, 'Matching_'.$unique, 'https://findme-'.$unique.'.test/x');
        $other = $this->createSource($user, 'Other_'.$unique, 'https://other.test/'.$unique);

        $this->requestSearch('findme-'.$unique);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($matching->name, $names);
        $this->assertNotContains($other->name, $names);
    }

    public function test_get_all_search_filter_treats_wildcards_literally(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $literal = $this->createSource($user, '100%_'.$unique, 'https://literal.test/'.$unique);
        $other = $this->createSource($user, 'Other_'.$unique, 'https://other.test/'.$unique);

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
        $literal = $this->createSource($user, 'A_b'.$unique, 'https://literal.test/'.$unique);
        $wildcard = $this->createSource($user, 'AXb'.$unique, 'https://wildcard.test/'.$unique);

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
        $match = $this->createSource($user, 'Alpha,Beta'.$unique, 'https://comma.test/'.$unique);
        $other = $this->createSource($user, 'Gamma'.$unique, 'https://other.test/'.$unique);

        // Spatie explodes comma-separated filter values into an array.
        $this->requestSearch('Alpha,Beta'.$unique);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($match->name, $names);
        $this->assertNotContains($other->name, $names);
    }

    public function test_get_all_search_filter_does_not_leak_another_users_source(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $unique = uniqid();
        $foreign = $this->createSource($otherUser, 'Shared'.$unique.'_theirs', 'https://foreign.test/'.$unique);
        $this->actingAs($user);
        $own = $this->createSource($user, 'Shared'.$unique.'_mine', 'https://own.test/'.$unique);

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
        $first = $this->createSource($user, 'AAA_'.$unique, 'https://aaa.test/'.$unique);
        $last = $this->createSource($user, 'ZZZ_'.$unique, 'https://zzz.test/'.$unique);

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
        $empty = $this->createSource($user, 'Empty_'.$unique, 'https://empty.test/'.$unique);
        $busy = $this->createSource($user, 'Busy_'.$unique, 'https://busy.test/'.$unique);

        NetworkProfile::factory()->count(2)->create([
            'user_id' => $user->id,
            'network_source_id' => $busy->id,
        ]);

        $this->requestQuery(['sort' => '-network_profiles_count']);
        $names = $this->repository->getAll(withCount: true, filterable: true)->pluck('name')->toArray();

        $this->assertLessThan(
            array_search($empty->name, $names, true),
            array_search($busy->name, $names, true)
        );
    }

    public function test_get_all_profiles_filter_isolates_sources_without_profiles(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $empty = $this->createSource($user, 'Empty_'.$unique, 'https://empty.test/'.$unique);
        $busy = $this->createSource($user, 'Busy_'.$unique, 'https://busy.test/'.$unique);

        NetworkProfile::factory()->count(2)->create([
            'user_id' => $user->id,
            'network_source_id' => $busy->id,
        ]);

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
        $empty = $this->createSource($user, 'Empty_'.$unique, 'https://empty.test/'.$unique);
        $inRange = $this->createSource($user, 'InRange_'.$unique, 'https://inrange.test/'.$unique);
        $tooMany = $this->createSource($user, 'TooMany_'.$unique, 'https://toomany.test/'.$unique);

        NetworkProfile::factory()->count(3)->create([
            'user_id' => $user->id,
            'network_source_id' => $inRange->id,
        ]);

        NetworkProfile::factory()->count(7)->create([
            'user_id' => $user->id,
            'network_source_id' => $tooMany->id,
        ]);

        $this->requestQuery(['filter' => ['profiles' => '1-5']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($inRange->name, $names);
        $this->assertNotContains($empty->name, $names);
        $this->assertNotContains($tooMany->name, $names);
    }

    public function test_get_all_profiles_filter_matches_the_open_ended_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $small = $this->createSource($user, 'Small_'.$unique, 'https://small.test/'.$unique);

        NetworkProfile::factory()->count(2)->create([
            'user_id' => $user->id,
            'network_source_id' => $small->id,
        ]);

        $this->requestQuery(['filter' => ['profiles' => '100+']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertNotContains($small->name, $names);
    }

    public function test_get_all_profiles_filter_matches_only_the_bucket_holding_the_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $source = $this->createSource($user, 'Eight_'.$unique, 'https://eight.test/'.$unique);

        NetworkProfile::factory()->count(8)->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
        ]);

        foreach (['0', '1-5', '6-10', '11-20', '21-50', '51-100', '100+'] as $range) {
            $this->requestQuery(['filter' => ['profiles' => $range]]);
            $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

            if ($range === '6-10') {
                $this->assertContains($source->name, $names, "range {$range} should match 8 profiles");

                continue;
            }

            $this->assertNotContains($source->name, $names, "range {$range} should not match 8 profiles");
        }
    }

    public function test_get_all_profiles_filter_ignores_an_unknown_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $source = $this->createSource($user, 'Kept_'.$unique, 'https://kept.test/'.$unique);

        $this->requestQuery(['filter' => ['profiles' => 'bogus']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($source->name, $names);
    }

    public function test_get_all_filters_by_excluded_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $included = NetworkSource::query()->create([
            'user_id' => $user->id,
            'name' => 'Included_'.$unique,
            'url' => 'https://included.test/'.$unique,
            'exclude_from_dashboard' => false,
        ]);
        $excluded = NetworkSource::query()->create([
            'user_id' => $user->id,
            'name' => 'Excluded_'.$unique,
            'url' => 'https://excluded.test/'.$unique,
            'exclude_from_dashboard' => true,
        ]);

        $this->requestQuery(['filter' => ['exclude_from_dashboard' => '1']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($excluded->name, $names);
        $this->assertNotContains($included->name, $names);
    }

    public function test_get_all_profiles_filter_does_not_count_another_users_profiles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $source = $this->createSource($user, 'Shared_'.$unique, 'https://shared.test/'.$unique);

        NetworkProfile::factory()->count(2)->create([
            'user_id' => $otherUser->id,
            'network_source_id' => $source->id,
        ]);

        // Only the authenticated user's profiles count towards the range.
        $this->requestQuery(['filter' => ['profiles' => '0']]);
        $names = $this->repository->getAll(filterable: true)->pluck('name')->toArray();

        $this->assertContains($source->name, $names);
    }

    public function test_get_all_ignores_request_filters_and_sorts_when_not_filterable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $unique = uniqid();
        $first = $this->createSource($user, 'AAA_'.$unique, 'https://aaa.test/'.$unique);
        $last = $this->createSource($user, 'ZZZ_'.$unique, 'https://zzz.test/'.$unique);

        // The dashboard reuses this repository for its source dropdown, where the
        // request carries profile filters and sorts that must not affect the
        // alphabetically ordered dropdown or be rejected by Spatie.
        $this->requestQuery([
            'filter' => ['search' => 'no_such_source_'.$unique],
            'sort' => '-last_visit_at',
        ]);
        $names = $this->repository->getAll()->pluck('name')->toArray();

        $this->assertSame([$first->name, $last->name], $names);
    }

    public function test_upsert_restore_applies_exclude_from_dashboard(): void
    {
        $user = User::factory()->create();
        $unique = uniqid();
        $name = 'Restore_'.$unique;
        $url = 'https://restore.com/'.$unique;

        NetworkSource::factory()->create([
            'user_id' => $user->id,
            'name' => $name,
            'url' => $url,
            'exclude_from_dashboard' => true,
            'deleted_at' => now(),
        ]);

        $result = $this->repository->upsert([
            'user_id' => $user->id,
            'name' => $name,
            'url' => $url,
            'exclude_from_dashboard' => false,
        ]);

        $this->assertFalse($result->exclude_from_dashboard);
    }

    /**
     * Creates a network source owned by the given user.
     */
    private function createSource(User $user, string $name, string $url): NetworkSource
    {
        return NetworkSource::query()->withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => $name,
            'url' => $url,
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
            Request::create('/network-sources', 'GET', $query)
        );
    }
}
