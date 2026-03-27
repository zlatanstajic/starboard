<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\NetworkSource;
use App\Repositories\NetworkSourceRepository;
use App\Services\NetworkSourceService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery\MockInterface;
use Override;
use Tests\TestCase;

class NetworkSourceServiceTest extends TestCase
{
    private NetworkSourceRepository|MockInterface $repository;

    private NetworkSourceService $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->mock(NetworkSourceRepository::class);
        $this->service = new NetworkSourceService($this->repository);
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

    public function test_get_all_passes_paginate_and_with_count_flags(): void
    {
        $paginator = new LengthAwarePaginator(collect(), 0, 10, 1);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(true, 'name', true)
            ->andReturn($paginator);

        $result = $this->service->getAll(paginate: true, withCount: true);

        $this->assertSame($paginator, $result);
    }

    public function test_create_delegates_to_repository_and_returns_network_source(): void
    {
        $data = ['name' => 'Test Source', 'url' => 'https://example.com/{username}'];
        $networkSource = new NetworkSource($data);

        $this->repository
            ->shouldReceive('upsert')
            ->once()
            ->with($data)
            ->andReturn($networkSource);

        $result = $this->service->create($data);

        $this->assertSame($networkSource, $result);
        $this->assertSame('Test Source', $result->name);
        $this->assertSame('https://example.com/{username}', $result->url);
    }

    public function test_update_delegates_to_repository_and_returns_updated_network_source(): void
    {
        $existingSource = new NetworkSource(['name' => 'Old Name', 'url' => 'https://old-url.com/{username}']);
        $data = ['name' => 'Updated Name', 'url' => 'https://updated-url.com/{username}'];
        $updatedSource = new NetworkSource($data);

        $this->repository
            ->shouldReceive('upsert')
            ->once()
            ->with($data, $existingSource)
            ->andReturn($updatedSource);

        $result = $this->service->update($existingSource, $data);

        $this->assertSame($updatedSource, $result);
        $this->assertSame('Updated Name', $result->name);
        $this->assertSame('https://updated-url.com/{username}', $result->url);
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
