<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\NetworkProfile;
use App\Repositories\NetworkProfileRepository;
use App\Services\NetworkProfileService;
use Illuminate\Database\Eloquent\Collection;
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
        $profilesCollection = new Collection([new NetworkProfile]);

        $mockedPaginator = new LengthAwarePaginator(
            $profilesCollection, // Your existing collection of mock profiles
            $profilesCollection->count(), // Total count
            20, // Per page
            1 // Current page
        );

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->andReturn($mockedPaginator);

        $result = $this->service->getAll();

        $this->assertSame($mockedPaginator, $result);
    }

    public function test_create_calls_upsert_without_id(): void
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
        $id = 99;
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

    public function test_delete_removes_record_from_database(): void
    {
        $networkProfile = NetworkProfile::factory()->create();

        $id = $networkProfile->id;

        $this->repository
            ->shouldReceive('delete')
            ->with($id)
            ->once()
            ->andReturn(true);

        $result = $this->service->delete($id);

        $this->assertTrue($result);
    }

    public function test_delete_returns_false_when_record_does_not_exist(): void
    {
        $id = 999;

        $this->repository
            ->shouldReceive('delete')
            ->with($id)
            ->once()
            ->andReturn(false);

        $result = $this->service->delete($id);

        $this->assertFalse($result);
    }

    public function test_record_visit_works_correctly(): void
    {
        // Freeze time to prevent millisecond mismatch during assertion
        Date::setTestNow(now()->startOfSecond());

        $initialNumberOfVisits = 5;
        $updatedNumberOfVisits = $initialNumberOfVisits + 1;
        $networkProfile = NetworkProfile::factory()->create([
            'number_of_visits' => $initialNumberOfVisits,
            'last_visit_at' => now()->subDays(1),
        ]);

        $networkProfileExpectation = $networkProfile;

        // Manually simulate what the repository would do.
        $networkProfileExpectation->number_of_visits = $updatedNumberOfVisits;
        $networkProfileExpectation->last_visit_at = now();

        // Define the expectation for the Mock
        $this->repository
            ->shouldReceive('increment')
            ->once()
            ->with($networkProfile)
            ->andReturn($networkProfileExpectation);

        $result = $this->service->recordVisit($networkProfile);

        $this->assertEquals($updatedNumberOfVisits, $result->number_of_visits);
        $this->assertEquals(now()->toDateTimeString(), $result->last_visit_at);
    }
}
