<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

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
        $this->assertEquals(10, $result->total());
        $this->assertEquals(1, $result->currentPage());
    }

    public function test_get_all_passes_paginate_flag(): void
    {
        $items = collect(['a']);
        $paginator = new LengthAwarePaginator($items, 1, 1, 1);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(true, 'name', false)
            ->andReturn($paginator);

        $result = $this->service->getAll(true);

        $this->assertSame($paginator, $result);
    }

    public function test_create_delegates_to_repository_and_returns_network_tag(): void
    {
        $data = ['name' => 'Test Tag', 'description' => 'desc'];
        $networkTag = new \App\Models\NetworkTag($data);

        $this->repository
            ->shouldReceive('upsert')
            ->once()
            ->with($data)
            ->andReturn($networkTag);

        $result = $this->service->create($data);

        $this->assertSame($networkTag, $result);
        $this->assertEquals('Test Tag', $result->name);
        $this->assertEquals('desc', $result->description);
    }

    public function test_update_delegates_to_repository_and_returns_updated_network_tag(): void
    {
        $existingTag = new \App\Models\NetworkTag(['name' => 'Old', 'description' => 'old']);
        $data = ['name' => 'Updated', 'description' => 'updated'];
        $updatedTag = new \App\Models\NetworkTag($data);

        $this->repository
            ->shouldReceive('upsert')
            ->once()
            ->with($data, $existingTag)
            ->andReturn($updatedTag);

        $result = $this->service->update($existingTag, $data);

        $this->assertSame($updatedTag, $result);
        $this->assertEquals('Updated', $result->name);
        $this->assertEquals('updated', $result->description);
    }

    public function test_delete_delegates_to_repository_and_returns_boolean(): void
    {
        $id = 1;

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($id)
            ->andReturn(true);

        $result = $this->service->delete($id);

        $this->assertTrue($result);
    }
}
