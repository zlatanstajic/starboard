<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\FilterList;
use App\Models\User;
use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Override;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Tests\TestCase;

class RepositoryTest extends TestCase
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

    public function test_owner_scoped_query_uses_the_explicit_owner_not_the_authenticated_user(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $visitor = User::withoutEvents(fn (): User => User::factory()->create());
        $owned = FilterList::factory()->create(['user_id' => $owner->id]);
        FilterList::factory()->create(['user_id' => $visitor->id]);
        $this->actingAs($visitor);
        $repository = new TestRepository;

        $results = $repository->owned(FilterList::class, $owner->id)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($owned));
    }

    public function test_build_standard_query_honors_the_injected_request(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $this->actingAs($owner);
        FilterList::factory()->create(['user_id' => $owner->id, 'is_published' => true]);
        FilterList::factory()->create(['user_id' => $owner->id, 'is_published' => false]);
        $this->app->instance('request', Request::create('/', 'GET', ['filter' => ['is_published' => '1']]));
        $repository = new TestRepository;

        $query = $repository->standard(
            FilterList::class,
            Request::create('/', 'GET', ['filter' => ['is_published' => '0']])
        );

        $this->assertCount(1, $query->get());
        $this->assertFalse($query->first()->is_published);
    }
}

class TestRepository extends Repository
{
    public function owned(string $modelClass, int $ownerId): Builder
    {
        return $this->ownerScopedQuery($modelClass::query(), $ownerId);
    }

    public function standard(string $modelClass, Request $request): QueryBuilder
    {
        return $this->buildStandardQuery(
            $modelClass,
            filters: [AllowedFilter::exact('is_published')],
            request: $request
        );
    }
}
