<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\NetworkTag;
use App\Models\User;
use App\Services\NetworkTagService;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Override;
use RealRashid\SweetAlert\Facades\Alert;
use Tests\TestCase;

class NetworkTagControllerTest extends TestCase
{
    private NetworkTagService|\Mockery\MockInterface $serviceMock;

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->serviceMock = $this->mock(NetworkTagService::class);
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

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), 'Service Error');

        $response = $this->get(route('network-tags.index'));

        $response->assertRedirect();
    }

    public function test_store_handles_service_exception(): void
    {
        $data = [
            'name' => 'TagName',
            'description' => 'Description',
            'user_id' => $this->user->id,
        ];

        $this->serviceMock
            ->shouldReceive('create')
            ->once()
            ->andThrow(new Exception('Creation Failed'));

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), 'Creation Failed');

        $response = $this->post(route('network-tags.store'), $data);

        $response->assertRedirect();
    }

    public function test_update_handles_service_exception(): void
    {
        $tag = NetworkTag::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => 'New Name', 'description' => 'New Desc'];

        $this->serviceMock
            ->shouldReceive('update')
            ->once()
            ->andThrow(new Exception('Update Failed'));

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), 'Update Failed');

        $response = $this->put(route('network-tags.update', $tag), $data);

        $response->assertRedirect();
    }

    public function test_index_returns_paginated_view(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 15);

        $this->serviceMock
            ->shouldReceive('getAll')
            ->once()
            ->andReturn($paginator);

        $response = $this->get(route('network-tags.index'));

        $response->assertOk();
        $response->assertViewIs('network-tags');
        $response->assertViewHas('networkTags', $paginator);
    }

    public function test_destroy_successfully_deletes_and_redirects(): void
    {
        $tag = NetworkTag::factory()->create(['user_id' => $this->user->id]);

        $this->serviceMock
            ->shouldReceive('delete')
            ->once()
            ->with($tag->id)
            ->andReturn(true);

        Alert::shouldReceive('error')->never();

        $response = $this->delete(route('network-tags.destroy', $tag));

        $response->assertRedirect();
    }

    public function test_destroy_handles_service_exception(): void
    {
        $tag = NetworkTag::factory()->create(['user_id' => $this->user->id]);

        $this->serviceMock
            ->shouldReceive('delete')
            ->once()
            ->with($tag->id)
            ->andThrow(new Exception('Deletion Failed'));

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), 'Deletion Failed');

        $response = $this->delete(route('network-tags.destroy', $tag));

        $response->assertRedirect();
    }

    public function test_destroy_handles_false_return_and_redirects(): void
    {
        $tag = NetworkTag::factory()->create(['user_id' => $this->user->id]);

        $this->serviceMock
            ->shouldReceive('delete')
            ->once()
            ->with($tag->id)
            ->andReturn(false);

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), __('messages.network_tag.deletion_failed'));

        $response = $this->delete(route('network-tags.destroy', $tag));

        $response->assertRedirect();
    }

    public function test_update_another_users_tag_returns_404(): void
    {
        $otherUser = User::factory()->create();
        $tag = NetworkTag::factory()->create(['user_id' => $otherUser->id]);

        $this->serviceMock->shouldNotReceive('update');

        $response = $this->put(route('network-tags.update', $tag), [
            'name' => 'Hijacked',
            'description' => 'Should not work',
        ]);

        $response->assertNotFound();
    }

    public function test_destroy_another_users_tag_returns_404(): void
    {
        $otherUser = User::factory()->create();
        $tag = NetworkTag::factory()->create(['user_id' => $otherUser->id]);

        $this->serviceMock->shouldNotReceive('delete');

        $response = $this->delete(route('network-tags.destroy', $tag));

        $response->assertNotFound();
    }
}
