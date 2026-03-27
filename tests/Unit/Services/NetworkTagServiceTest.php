<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\NetworkTag;
use App\Repositories\NetworkTagRepository;
use App\Services\NetworkTagService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery\MockInterface;
use Override;
use Tests\TestCase;

class NetworkTagServiceTest extends TestCase
{
    private NetworkTagRepository|MockInterface $repository;

    private NetworkTagService $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->mock(NetworkTagRepository::class);
        $this->service = new NetworkTagService($this->repository);
    }

    public function test_get_all_delegates_to_repository_returning_paginator(): void
    {
        $items = collect(['item1', 'item2']);
        $paginator = new LengthAwarePaginator($items, 10, 2, 1);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(false, 'name', false)
            ->andReturn($paginator);

        $result = $this->service->getAll();

        $this->assertSame($paginator, $result);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(10, $result->total());
        $this->assertSame(1, $result->currentPage());
    }

    public function test_get_all_passes_paginate_flag(): void
    {
        $paginator = new LengthAwarePaginator(collect(['a']), 1, 1, 1);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(true, 'name', false)
            ->andReturn($paginator);

        $result = $this->service->getAll(true);

        $this->assertSame($paginator, $result);
    }

    public function test_get_all_passes_with_count_flag(): void
    {
        $paginator = new LengthAwarePaginator(collect(), 0, 10, 1);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(false, 'name', true)
            ->andReturn($paginator);

        $result = $this->service->getAll(withCount: true);

        $this->assertSame($paginator, $result);
    }

    public function test_create_delegates_to_repository_and_returns_network_tag(): void
    {
        $data = ['name' => 'Test Tag', 'description' => 'desc'];
        $networkTag = new NetworkTag($data);

        $this->repository
            ->shouldReceive('upsert')
            ->once()
            ->with($data)
            ->andReturn($networkTag);

        $result = $this->service->create($data);

        $this->assertSame($networkTag, $result);
        $this->assertSame('Test Tag', $result->name);
        $this->assertSame('desc', $result->description);
    }

    public function test_update_delegates_to_repository_and_returns_updated_network_tag(): void
    {
        $existingTag = new NetworkTag(['name' => 'Old', 'description' => 'old']);
        $data = ['name' => 'Updated', 'description' => 'updated'];
        $updatedTag = new NetworkTag($data);

        $this->repository
            ->shouldReceive('upsert')
            ->once()
            ->with($data, $existingTag)
            ->andReturn($updatedTag);

        $result = $this->service->update($existingTag, $data);

        $this->assertSame($updatedTag, $result);
        $this->assertSame('Updated', $result->name);
        $this->assertSame('updated', $result->description);
    }

    public function test_delete_delegates_to_repository_and_returns_true(): void
    {
        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $this->assertTrue($this->service->delete(1));
    }

    public function test_delete_returns_false_when_repository_fails(): void
    {
        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with(999)
            ->andReturn(false);

        $this->assertFalse($this->service->delete(999));
    }
}
