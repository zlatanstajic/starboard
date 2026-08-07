<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use App\Repositories\NetworkSourceRepository;
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

    public function test_index_displays_default_icon_for_source_without_icon(): void
    {
        $source = NetworkSource::factory()->make([
            'id' => 1,
            'user_id' => $this->user->id,
            'name' => 'IMDB List',
            'url' => 'https://www.imdb.com/list/{id}',
            'icon' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $source->network_profiles_count = 0;

        $paginator = new LengthAwarePaginator([$source], 1, 15);

        $this->serviceMock
            ->shouldReceive('getAll')
            ->once()
            ->andReturn($paginator);

        $response = $this->get(route('network-sources.index'));

        $response->assertOk();
        $response->assertSee('fill="currentColor"', false);
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

    public function test_index_narrows_the_listing_with_the_search_filter(): void
    {
        $this->useRealService();
        $matching = $this->createSource('Matching_'.uniqid(), 'https://matching.test/{username}');
        $other = $this->createSource('Other_'.uniqid(), 'https://other.test/{username}');

        $response = $this->get(route('network-sources.index', [
            'filter' => ['search' => $matching->name],
        ]));

        $response->assertOk();
        $response->assertSee($matching->name);
        $response->assertDontSee($other->name);
    }

    public function test_index_sorts_the_listing_in_reverse_name_order(): void
    {
        $this->useRealService();
        $unique = uniqid();
        $first = $this->createSource('AAA_'.$unique, 'https://aaa.test/{username}');
        $last = $this->createSource('ZZZ_'.$unique, 'https://zzz.test/{username}');

        $response = $this->get(route('network-sources.index', ['sort' => '-name']));

        $response->assertOk();
        $response->assertSeeInOrder([$last->name, $first->name]);
    }

    public function test_index_sorts_the_listing_by_network_profiles_count(): void
    {
        $this->useRealService();
        $unique = uniqid();
        $empty = $this->createSource('Empty_'.$unique, 'https://empty.test/{username}');
        $busy = $this->createSource('Busy_'.$unique, 'https://busy.test/{username}');

        NetworkProfile::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'network_source_id' => $busy->id,
        ]);

        $response = $this->get(route('network-sources.index', ['sort' => '-network_profiles_count']));

        $response->assertOk();
        $response->assertSeeInOrder([$busy->name, $empty->name]);
    }

    public function test_index_filters_the_listing_by_profile_count_range(): void
    {
        $this->useRealService();
        $unique = uniqid();
        $empty = $this->createSource('Empty_'.$unique, 'https://empty.test/{username}');
        $busy = $this->createSource('Busy_'.$unique, 'https://busy.test/{username}');

        NetworkProfile::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'network_source_id' => $busy->id,
        ]);

        $response = $this->get(route('network-sources.index', ['filter' => ['profiles' => '0']]));

        $response->assertOk();
        $response->assertSee($empty->name);
        $response->assertDontSee($busy->name);
    }

    public function test_index_filters_the_listing_by_source_status(): void
    {
        $this->useRealService();
        $unique = uniqid();
        $included = NetworkSource::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Included_'.$unique,
            'exclude_from_dashboard' => false,
        ]);
        $excluded = NetworkSource::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Excluded_'.$unique,
            'exclude_from_dashboard' => true,
        ]);

        $includedResponse = $this->get(route('network-sources.index', [
            'filter' => ['exclude_from_dashboard' => '0'],
        ]));

        $includedResponse->assertOk();
        $includedResponse->assertSee($included->name);
        $includedResponse->assertDontSee($excluded->name);

        $excludedResponse = $this->get(route('network-sources.index', [
            'filter' => ['exclude_from_dashboard' => '1'],
        ]));

        $excludedResponse->assertOk();
        $excludedResponse->assertSee($excluded->name);
        $excludedResponse->assertDontSee($included->name);
    }

    public function test_index_redirects_when_the_sort_is_not_allowed(): void
    {
        $this->useRealService();

        $response = $this->get(route('network-sources.index', ['sort' => 'bogus']));

        $response->assertRedirect(route('network-sources.index'));
    }

    public function test_index_without_a_query_lists_every_source(): void
    {
        $this->useRealService();
        $unique = uniqid();
        $first = $this->createSource('First_'.$unique, 'https://first.test/{username}');
        $second = $this->createSource('Second_'.$unique, 'https://second.test/{username}');

        $response = $this->get(route('network-sources.index'));

        $response->assertOk();
        $response->assertSee($first->name);
        $response->assertSee($second->name);
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

    /**
     * Swaps the service mock for the real service so the listing query
     * (search, sort) is actually executed against the database.
     */
    private function useRealService(): void
    {
        $this->app->forgetInstance(NetworkSourceService::class);
        $this->instance(
            NetworkSourceService::class,
            new NetworkSourceService(new NetworkSourceRepository)
        );
    }

    /**
     * Creates a network source owned by the acting user.
     */
    private function createSource(string $name, string $url): NetworkSource
    {
        return NetworkSource::query()->create([
            'user_id' => $this->user->id,
            'name' => $name,
            'url' => $url,
        ]);
    }
}
