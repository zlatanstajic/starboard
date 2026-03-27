<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\NetworkProfile;
use App\Repositories\NetworkProfileRepository;
use App\Services\NetworkProfileService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;
use Mockery\MockInterface;
use Override;
use Tests\TestCase;

class NetworkProfileServiceTest extends TestCase
{
    private NetworkProfileRepository|MockInterface $repository;

    private NetworkProfileService $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->mock(NetworkProfileRepository::class);
        $this->service = new NetworkProfileService($this->repository);
    }

    public function test_get_all_delegates_to_repository(): void
    {
        $paginator = new LengthAwarePaginator(
            collect([new NetworkProfile]),
            1,
            20,
            1
        );

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->andReturn($paginator);

        $result = $this->service->getAll();

        $this->assertSame($paginator, $result);
    }

    public function test_create_calls_upsert_without_existing_profile(): void
    {
        $data = ['name' => 'New Profile'];
        $networkProfile = new NetworkProfile;

        $this->repository
            ->shouldReceive('upsert')
            ->with($data)
            ->once()
            ->andReturn($networkProfile);

        $result = $this->service->create($data);

        $this->assertSame($networkProfile, $result);
    }

    public function test_update_calls_upsert_with_network_profile(): void
    {
        $data = ['name' => 'Updated Name'];
        $networkProfile = new NetworkProfile;

        $this->repository
            ->shouldReceive('upsert')
            ->with($data, $networkProfile)
            ->once()
            ->andReturn($networkProfile);

        $result = $this->service->update($networkProfile, $data);

        $this->assertSame($networkProfile, $result);
    }

    public function test_delete_returns_true_on_success(): void
    {
        $this->repository
            ->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->service->delete(1));
    }

    public function test_delete_returns_false_when_record_does_not_exist(): void
    {
        $this->repository
            ->shouldReceive('delete')
            ->with(999)
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->service->delete(999));
    }

    public function test_record_visit_delegates_to_repository_increment(): void
    {
        Date::setTestNow(now()->startOfSecond());

        $networkProfile = NetworkProfile::factory()->create([
            'number_of_visits' => 5,
            'last_visit_at' => now()->subDays(1),
        ]);

        $expectedProfile = $networkProfile;
        $expectedProfile->number_of_visits = 6;
        $expectedProfile->last_visit_at = now();

        $this->repository
            ->shouldReceive('increment')
            ->once()
            ->with($networkProfile)
            ->andReturn($expectedProfile);

        $result = $this->service->recordVisit($networkProfile);

        $this->assertSame(6, $result->number_of_visits);
        $this->assertSame(now()->toDateTimeString(), $result->last_visit_at->toDateTimeString());
    }
}
