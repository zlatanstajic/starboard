<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use App\Services\NetworkProfileService;
use App\Services\NetworkSourceService;
use Exception;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Mockery;
use Override;
use RealRashid\SweetAlert\Facades\Alert;
use Tests\TestCase;

class NetworkProfileControllerTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Disable only CSRF so requests can be made without tokens but keep route bindings
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_index_returns_view_with_data(): void
    {
        $user = User::factory()->create();

        // Clean up existing network sources to avoid conflict - force delete to handle soft deletes
        NetworkSource::query()->withoutGlobalScopes()->where('url', 'https://instagram.com/{username}')->forceDelete();
        NetworkSource::query()->withoutGlobalScopes()->where('url', 'https://tiktok.com/@{username}')->forceDelete();

        // Create real network sources that will be returned by the service
        $instagram = NetworkSource::query()->create(['user_id' => $user->id, 'name' => 'test_instagram_'.uniqid(), 'url' => 'https://instagram.com/{username}']);
        $tiktok = NetworkSource::query()->create(['user_id' => $user->id, 'name' => 'test_tiktok_'.uniqid(), 'url' => 'https://tiktok.com/@{username}']);

        // Create profiles with the real network sources
        $profiles = NetworkProfile::factory()->count(3)->create([
            'user_id' => $user->id,
            'network_source_id' => $instagram->id,
        ]);

        // Make the request as the user
        $response = $this->actingAs($user)->get(route('dashboard'));

        // Assert the response is valid
        $response->assertOk();
        $response->assertViewIs('dashboard');
        $response->assertViewHas('networkSources');
        $response->assertViewHas('networkProfiles');
    }

    public function test_index_handles_exception_and_redirects(): void
    {
        $user = User::factory()->create();

        $networkSourceService = Mockery::mock(NetworkSourceService::class);
        $networkSourceService->shouldReceive('getAll')->once()->andThrow(new Exception('boom'));

        $this->app->instance(NetworkSourceService::class, $networkSourceService);

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), 'boom');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_store_calls_service_and_redirects(): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();

        $payload = [
            'network_source_id' => $networkSource->id,
            'username' => 'alice',
            'is_public' => true,
            'is_favorite' => false,
        ];

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg['user_id'] === $user->id
                && $arg['username'] === $payload['username']
                && $arg['network_source_id'] === $payload['network_source_id']))
            ->andReturn(NetworkProfile::factory()->make());

        $this->app->instance(NetworkProfileService::class, $service);

        $response = $this->actingAs($user)
            ->post(route('network-profiles.store'), $payload);

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_store_handles_exception_and_shows_alert(): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();

        $payload = [
            'network_source_id' => $networkSource->id,
            'username' => 'bob',
            'is_public' => true,
            'is_favorite' => false,
        ];

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldReceive('create')->once()->andThrow(new Exception('boom'));

        $this->app->instance(NetworkProfileService::class, $service);

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), 'boom');

        $response = $this->actingAs($user)
            ->post(route('network-profiles.store'), $payload);

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_update_calls_service_and_redirects(): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();
        $networkProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'username' => 'original',
        ]);

        $payload = [
            'username' => 'updated',
        ];

        $response = $this->actingAs($user)
            ->put(route('network-profiles.update', ['networkProfile' => $networkProfile->id]), $payload);

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));

        $networkProfile->refresh();
        $this->assertEquals('updated', $networkProfile->username);
    }

    public function test_destroy_when_delete_fails_shows_alert_and_redirects(): void
    {
        $user = User::factory()->create();
        $networkProfile = NetworkProfile::factory()->create(['user_id' => $user->id]);

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldReceive('delete')->once()->with($networkProfile->id)->andReturn(false);

        $this->app->instance(NetworkProfileService::class, $service);

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), __('messages.network_profile.deletion_failed'));

        $response = $this->actingAs($user)
            ->delete(route('network-profiles.destroy', ['networkProfile' => $networkProfile->id]));

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_record_visit_calls_service_and_redirects(): void
    {
        $user = User::factory()->create();
        $networkProfile = NetworkProfile::factory()->create(['user_id' => $user->id]);

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldReceive('recordVisit')->once()->with(Mockery::on(fn ($arg) => $arg instanceof NetworkProfile && $arg->id === $networkProfile->id))->andReturn($networkProfile);

        $this->app->instance(NetworkProfileService::class, $service);

        $response = $this->actingAs($user)
            ->post(route('network-profiles.recordVisit', ['networkProfile' => $networkProfile->id]));

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_update_handles_exception_and_shows_alert(): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();
        $networkProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
        ]);

        $payload = [
            'username' => 'updated',
        ];

        $service = Mockery::mock(NetworkProfileService::class);
        // Be permissive on the model instance to avoid route-model binding instance mismatch during tests
        $service->shouldReceive('update')
            ->once()
            ->with(Mockery::any(), Mockery::on(fn ($arg) => isset($arg['username']) && $arg['username'] === $payload['username']))
            ->andThrow(new Exception('boom'));

        $this->app->instance(NetworkProfileService::class, $service);

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), 'boom');

        $response = $this->actingAs($user)
            ->put(route('network-profiles.update', ['networkProfile' => $networkProfile->id]), $payload);

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_record_visit_handles_exception_and_shows_alert(): void
    {
        $user = User::factory()->create();
        $networkProfile = NetworkProfile::factory()->create(['user_id' => $user->id]);

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldReceive('recordVisit')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg instanceof NetworkProfile && $arg->id === $networkProfile->id))
            ->andThrow(new Exception('boom'));

        $this->app->instance(NetworkProfileService::class, $service);

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), 'boom');

        $response = $this->actingAs($user)
            ->post(route('network-profiles.recordVisit', ['networkProfile' => $networkProfile->id]));

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));
    }
}
