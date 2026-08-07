<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\FilterList\FilterListHashGenerationException;
use App\Models\FilterList;
use App\Models\User;
use App\Repositories\FilterListRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Override;
use Tests\TestCase;

class FilterListRepositoryTest extends TestCase
{
    private FilterListRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->repository = new FilterListRepository;
    }

    #[Override]
    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();
        DB::rollBack();
        parent::tearDown();
    }

    public function test_create_mints_a_hash_that_does_not_reuse_a_trashed_hash(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $otherOwner = User::withoutEvents(fn (): User => User::factory()->create());
        $collision = 'Collision123';
        FilterList::factory()->create(['user_id' => $owner->id, 'hash' => $collision])->delete();
        $this->actingAs($otherOwner);
        Str::createRandomStringsUsingSequence([$collision, 'FreshHash123']);

        $list = $this->repository->upsert([
            'user_id' => $otherOwner->id,
            'name' => 'Fresh list',
            'filters' => ['filter' => ['is_public' => '1']],
        ]);

        $this->assertSame('FreshHash123', $list->hash);
        $this->assertTrue($list->is_published);
        $this->assertNotNull($list->published_at);
    }

    public function test_duplicate_hash_is_converted_to_a_domain_exception(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $this->actingAs($owner);
        FilterList::factory()->create(['user_id' => $owner->id, 'hash' => 'TakenHash123']);
        $other = FilterList::factory()->create(['user_id' => $owner->id, 'hash' => 'OtherHash123']);
        $this->expectException(FilterListHashGenerationException::class);

        $this->repository->upsert(['hash' => 'TakenHash123'], $other);
    }

    public function test_search_accepts_a_comma_bearing_term(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $this->actingAs($owner);
        FilterList::factory()->create(['user_id' => $owner->id, 'name' => 'Alpha, Beta']);
        $this->app->instance('request', Request::create('/', 'GET', ['filter' => ['search' => 'Alpha, Beta']]));

        $results = $this->repository->getAll(filterable: true);

        $this->assertCount(1, $results);
    }

    public function test_unfiltered_unpaginated_listing_uses_the_default_order(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $this->actingAs($owner);
        FilterList::factory()->count(2)->create(['user_id' => $owner->id]);

        $results = $this->repository->getAll();

        $this->assertGreaterThanOrEqual(2, $results->total());
    }

    public function test_upsert_rethrows_non_constraint_exceptions(): void
    {
        $list = $this->getMockBuilder(FilterList::class)
            ->onlyMethods(['update'])
            ->getMock();
        $list->method('update')->willThrowException(new Exception('Update failed', 500));
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Update failed');

        $this->repository->upsert(['name' => 'Broken'], $list);
    }

    public function test_public_lookup_hides_unpublished_and_trashed_rows(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $unpublished = FilterList::factory()->create([
            'user_id' => $owner->id,
            'hash' => 'Unpublish123',
            'is_published' => false,
        ]);
        $trashed = FilterList::factory()->create(['user_id' => $owner->id, 'hash' => 'DeletedHash12']);
        $trashed->delete();

        $this->assertNull($this->repository->getPublishedByHash($unpublished->hash));
        $this->assertNull($this->repository->getPublishedByHash($trashed->hash));
    }

    public function test_latest_published_spans_users_and_excludes_hidden_rows(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $otherOwner = User::withoutEvents(fn (): User => User::factory()->create());
        $newest = FilterList::factory()->create([
            'user_id' => $otherOwner->id,
            'published_at' => now(),
        ]);
        $older = FilterList::factory()->create([
            'user_id' => $owner->id,
            'published_at' => now()->subDay(),
        ]);
        $unpublished = FilterList::factory()->create([
            'user_id' => $owner->id,
            'is_published' => false,
        ]);
        $trashed = FilterList::factory()->create(['user_id' => $owner->id]);
        $trashed->delete();
        $this->actingAs($owner);

        $lists = $this->repository->getLatestPublished(10);
        $ids = $lists->pluck('id')->all();

        $this->assertSame([$newest->id, $older->id], $ids);
        $this->assertNotContains($unpublished->id, $ids);
        $this->assertNotContains($trashed->id, $ids);
    }

    public function test_latest_published_respects_the_requested_limit(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        FilterList::factory()->count(3)->create(['user_id' => $owner->id]);

        $this->assertCount(2, $this->repository->getLatestPublished(2));
    }

    public function test_latest_published_breaks_a_published_at_tie_by_id(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $sharedTimestamp = now()->subHour();
        $first = FilterList::factory()->create([
            'user_id' => $owner->id,
            'published_at' => $sharedTimestamp,
        ]);
        $second = FilterList::factory()->create([
            'user_id' => $owner->id,
            'published_at' => $sharedTimestamp,
        ]);

        $ids = $this->repository->getLatestPublished(10)->pluck('id')->all();

        $this->assertSame([$second->id, $first->id], $ids);
    }

    public function test_unpublishing_keeps_the_original_published_at(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $firstPublishedAt = now()->subWeek()->startOfSecond();
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'published_at' => $firstPublishedAt,
        ]);

        $unpublished = $this->repository->setPublished($list, false);

        $this->assertFalse($unpublished->is_published);
        $this->assertTrue($firstPublishedAt->equalTo($unpublished->published_at));
        $this->assertTrue($this->repository->delete($list->id));
    }

    public function test_republishing_does_not_refresh_published_at(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $firstPublishedAt = now()->subMonth()->startOfSecond();
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'is_published' => false,
            'published_at' => $firstPublishedAt,
        ]);

        $republished = $this->repository->setPublished($list, true, 'FreshHash123');

        $this->assertTrue($republished->is_published);
        $this->assertSame('FreshHash123', $republished->hash);
        $this->assertTrue($firstPublishedAt->equalTo($republished->published_at));
    }

    public function test_publishing_a_never_published_list_stamps_published_at(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'is_published' => false,
            'published_at' => null,
        ]);

        $published = $this->repository->setPublished($list, true);

        $this->assertTrue($published->is_published);
        $this->assertNotNull($published->published_at);
    }

    public function test_an_integer_constraint_code_still_maps_to_the_domain_exception(): void
    {
        $list = $this->getMockBuilder(FilterList::class)
            ->onlyMethods(['update'])
            ->getMock();
        $list->method('update')->willThrowException(new Exception('Duplicate entry', 23000));
        $this->expectException(FilterListHashGenerationException::class);

        $this->repository->upsert(['hash' => 'TakenHash123'], $list);
    }

    public function test_a_republish_hash_collision_maps_to_the_domain_exception(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $taken = FilterList::factory()->create(['user_id' => $owner->id, 'hash' => 'TakenHash123']);
        $taken->delete();
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'is_published' => false,
        ]);
        $this->expectException(FilterListHashGenerationException::class);

        $this->repository->setPublished($list, true, 'TakenHash123');
    }

    public function test_hash_generation_fails_after_the_bounded_attempts_are_exhausted(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $collision = 'Collision123';
        FilterList::factory()->create(['user_id' => $owner->id, 'hash' => $collision]);
        Str::createRandomStringsUsingSequence(array_fill(0, 10, $collision));
        $this->expectException(FilterListHashGenerationException::class);

        $this->repository->mintHash();
    }
}
