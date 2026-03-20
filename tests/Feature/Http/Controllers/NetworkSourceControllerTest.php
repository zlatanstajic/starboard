<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\NetworkSource;
use App\Models\User;
use App\Services\NetworkSourceService;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Override;
use RealRashid\SweetAlert\Facades\Alert;
use Tests\TestCase;

class NetworkSourceControllerTest extends TestCase
{
    private NetworkSourceService|\Mockery\MockInterface $serviceMock;

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->serviceMock = $this->mock(NetworkSourceService::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_index_handles_service_exception(): void
    {
        $this->serviceMock
            ->shouldReceive('getAll')
            ->once()
            ->andThrow(new Exception('Service Error'));

        $response = $this->get(route('network-sources.index'));

        $response->assertRedirect();
    }

    public function test_store_handles_service_exception(): void
    {
        $data = [
            'name' => 'Twitter',
            'url' => 'https://twitter.com',
            'user_id' => $this->user->id,
        ];

        $this->serviceMock
            ->shouldReceive('create')
            ->once()
            ->andThrow(new Exception('Creation Failed'));

        $response = $this->post(route('network-sources.store'), $data);

        $response->assertRedirect();
    }

    public function test_update_handles_service_exception(): void
    {
        $source = NetworkSource::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => 'New Name', 'url' => 'https://newurl.com'];

        $this->serviceMock
            ->shouldReceive('update')
            ->once()
            ->andThrow(new Exception('Update Failed'));

        $response = $this->put(route('network-sources.update', $source), $data);

        $response->assertRedirect();
    }

    public function test_index_returns_paginated_view(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 15);

        $this->serviceMock
            ->shouldReceive('getAll')
            ->once()
            ->andReturn($paginator);

        $response = $this->get(route('network-sources.index'));

        $response->assertOk();
        $response->assertViewIs('network-sources');
        $response->assertViewHas('networkSources', $paginator);
    }

    public function test_destroy_successfully_deletes_and_redirects(): void
    {
        $source = NetworkSource::factory()->create(['user_id' => $this->user->id]);

        $this->serviceMock
            ->shouldReceive('delete')
            ->once()
            ->with($source->id)
            ->andReturn(true);

        $response = $this->delete(route('network-sources.destroy', $source));

        $response->assertRedirect();
    }

    public function test_destroy_handles_service_exception(): void
    {
        $source = NetworkSource::factory()->create(['user_id' => $this->user->id]);

        $this->serviceMock
            ->shouldReceive('delete')
            ->once()
            ->with($source->id)
            ->andThrow(new Exception('Deletion Failed'));

        $response = $this->delete(route('network-sources.destroy', $source));

        $response->assertRedirect();
    }

    public function test_destroy_handles_false_return_and_redirects(): void
    {
        $source = NetworkSource::factory()->create(['user_id' => $this->user->id]);

        $this->serviceMock
            ->shouldReceive('delete')
            ->once()
            ->with($source->id)
            ->andReturn(false);

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), __('messages.network_source.deletion_failed'));

        $response = $this->delete(route('network-sources.destroy', $source));

        $response->assertRedirect();
    }
}
