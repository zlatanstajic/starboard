<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\NetworkSource\NetworkSourceDuplicationException;
use App\Models\NetworkSource;
use App\Models\User;
use App\Repositories\NetworkSourceRepository;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
}
