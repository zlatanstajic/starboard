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
}
