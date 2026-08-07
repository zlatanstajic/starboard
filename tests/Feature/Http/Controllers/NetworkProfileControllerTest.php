<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\FilterList;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;
use App\Services\FilterListService;
use App\Services\NetworkProfileService;
use App\Services\NetworkSourceService;
use Exception;
use Illuminate\Bus\Batch;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Mockery;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use RealRashid\SweetAlert\Facades\Alert;
use Tests\TestCase;

class NetworkProfileControllerTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Disable only CSRF so requests can be made without tokens but keep route bindings
        $this->withoutMiddleware(PreventRequestForgery::class);
        config()->set('youtube.execution_enabled', true);
        config()->set('youtube.ui_enabled', true);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function storeUsernameNormalizationProvider(): array
    {
        return [
            'strips single leading at' => ['@MrBeast', 'MrBeast'],
            'leaves bare handle unchanged' => ['MrBeast', 'MrBeast'],
            'strips only one leading at' => ['@@MrBeast', '@MrBeast'],
            'preserves non-leading at' => ['Mr@Beast', 'Mr@Beast'],
            'trims surrounding spaces' => ['  @MrBeast  ', 'MrBeast'],
            'trims spaces exposed after at' => ['@ MrBeast', 'MrBeast'],
            'strips http prefix' => ['http://example.com/profile', 'example.com/profile'],
            'strips https prefix' => ['https://example.com/profile', 'example.com/profile'],
            'strips case-insensitive scheme' => ['HTTPS://Example.com/profile', 'Example.com/profile'],
            'strips only one leading scheme' => ['https://https://example.com', 'https://example.com'],
            'preserves non-leading scheme' => ['profile-https://example.com', 'profile-https://example.com'],
            'trims spaces exposed after scheme' => ['https:// example.com/profile', 'example.com/profile'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function updateUsernameNormalizationProvider(): array
    {
        return [
            'strips single leading at' => ['@updated', 'updated'],
            'strips only one leading at' => ['@@updated', '@updated'],
            'trims surrounding spaces' => ['  @updated  ', 'updated'],
            'strips http prefix' => ['http://example.com/updated', 'example.com/updated'],
            'strips case-insensitive https prefix' => ['  HTTPS://Example.com/updated  ', 'Example.com/updated'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function bareUrlSchemeProvider(): array
    {
        return [
            'http scheme' => ['http://'],
            'https scheme' => ['https://'],
            'case-insensitive scheme with whitespace' => ['  HTTPS://  '],
        ];
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

    public function test_dashboard_renders_column_visibility_control(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);

        // A tagged profile exercises the non-empty Tags <td>; an untagged one exercises the empty Tags <td>.
        $taggedProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
        ]);
        $tag = NetworkTag::factory()->create(['user_id' => $user->id]);
        $taggedProfile->networkTags()->attach($tag->id);

        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard');

        // The Columns control label and the readable "row number" checkbox label render.
        $response->assertSee(__('messages.default.columns'), false);
        $response->assertSee(__('messages.default.row_number'), false);

        // Columns and Fetch are rendered in the filters-only actions row.
        $response->assertSeeInOrder([
            'data-filter-actions',
            __('messages.default.columns'),
            'x-show="!busy"',
            __('messages.default.fetch'),
            __('messages.default.clear'),
        ], false);

        // The localStorage persistence key is seeded on the root Alpine scope.
        $response->assertSee('dashboard_columns', false);

        // The Name column is guarded: its checkbox renders disabled and checked (hide-all guard).
        $response->assertSee('type="checkbox" checked disabled', false);

        // Every column exposes a stable data-col hook on its header and body cells.
        foreach (['number', 'name', 'tags', 'status', 'favorite', 'visits', 'timestamps', 'actions'] as $key) {
            $response->assertSee('data-col="'.$key.'"', false);
        }

        // Every toggleable column (all except the always-on Name) binds a checkbox via x-model.
        foreach (['number', 'tags', 'status', 'favorite', 'visits', 'timestamps', 'actions'] as $key) {
            $response->assertSee('x-model="columns.'.$key.'"', false);
        }
    }

    public function test_dashboard_renders_stable_height_tag_filters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'data-tag-filter="included"',
            'id="filter-tags-select"',
            'data-tag-filter="excluded"',
            'id="filter-exclude-tags-select"',
        ], false);
    }

    public function test_dashboard_places_the_tags_filter_between_sort_and_descriptions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        // First row holds the source/visits/time/status/favourite selects; the second row runs
        // sort -> tags -> descriptions -> search, so the tags filter must follow the sort select.
        $response->assertSeeInOrder([
            'data-network-source-filter',
            'aria-label="'.__('messages.network_profile.filter.all_visits').'"',
            'aria-label="'.__('messages.network_profile.filter.all_statuses').'"',
            'aria-label="'.__('messages.default.default_sort').'"',
            'aria-label="'.__('messages.network_profile.filter.all_tags').'"',
            'aria-label="'.__('messages.network_profile.filter.all_descriptions').'"',
            'id="search-form"',
        ], false);
    }

    public function test_dashboard_applies_a_saved_filter_list_capture_from_the_generated_url(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);
        $matchingA = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'username' => 'needle-alpha',
        ]);
        $matchingB = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'username' => 'needle-beta',
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'username' => 'unrelated-profile',
        ]);
        $filterList = FilterList::factory()->create([
            'user_id' => $user->id,
            'is_published' => false,
            'published_at' => null,
            'filters' => [
                'filter' => ['search' => 'needle', 'network_source_id' => (string) $source->id],
                'sort' => '-username',
            ],
        ]);
        $applyUrl = resolve(FilterListService::class)->buildDashboardUrl($filterList);

        $response = $this->actingAs($user)->get($applyUrl);

        $response->assertOk();
        $response->assertDontSee('unrelated-profile');
        // '-username' sorts descending, so beta precedes alpha.
        $response->assertSeeInOrder([$matchingB->username, $matchingA->username], false);

        // This test class runs outside a transaction, so the seeded list is
        // removed by hand to keep it out of the cross-user published read.
        $filterList->forceDelete();
    }

    public function test_dashboard_apply_url_keeps_private_and_excluded_source_profiles_visible(): void
    {
        $user = User::factory()->create();
        $excludedSource = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'exclude_from_dashboard' => true,
        ]);
        $private = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $excludedSource->id,
            'username' => 'needle-private',
            'is_public' => false,
        ]);
        $filterList = FilterList::factory()->create([
            'user_id' => $user->id,
            'is_published' => false,
            'published_at' => null,
            'filters' => [
                // The source is pinned, which is what lifts the dashboard's own
                // implicit exclude_from_dashboard narrowing (see getAll()).
                'filter' => ['search' => 'needle', 'network_source_id' => (string) $excludedSource->id],
            ],
        ]);
        $applyUrl = resolve(FilterListService::class)->buildDashboardUrl($filterList);

        $response = $this->actingAs($user)->get($applyUrl);

        $response->assertOk();
        // No is_public narrowing is inherited from the public list page.
        $response->assertSee($private->username);

        $filterList->forceDelete();
    }

    public function test_dashboard_apply_url_round_trips_an_array_valued_tags_capture(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);
        $tagA = NetworkTag::factory()->create(['user_id' => $user->id]);
        $tagB = NetworkTag::factory()->create(['user_id' => $user->id]);
        // The tags filter is strict AND, so only the profile carrying both tags
        // may match; the single-tag profile is what a comma-joined regression
        // (both ids collapsed into one scalar) would wrongly pull in.
        $bothTags = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'username' => 'tagged-with-both',
        ]);
        $bothTags->networkTags()->attach([$tagA->id, $tagB->id]);
        $oneTag = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'username' => 'tagged-with-one',
        ]);
        $oneTag->networkTags()->attach($tagA->id);
        $filterList = FilterList::factory()->create([
            'user_id' => $user->id,
            'is_published' => false,
            'published_at' => null,
            'filters' => ['filter' => ['tags' => [(string) $tagA->id, (string) $tagB->id]]],
        ]);
        $applyUrl = resolve(FilterListService::class)->buildDashboardUrl($filterList);

        parse_str((string) parse_url($applyUrl, PHP_URL_QUERY), $query);
        $this->assertSame([(string) $tagA->id, (string) $tagB->id], $query['filter']['tags']);

        $response = $this->actingAs($user)->get($applyUrl);

        $response->assertOk();
        $response->assertSee('tagged-with-both');
        $response->assertDontSee('tagged-with-one');

        $filterList->forceDelete();
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

    public function test_fetch_dispatches_batch_and_stores_batch_id(): void
    {
        $user = User::factory()->create();

        $batch = Mockery::mock(Batch::class);
        $batch->id = 'batch-abc-123';

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldReceive('fetchNewItems')->once()->andReturn($batch);

        $this->app->instance(NetworkProfileService::class, $service);

        $response = $this->actingAs($user)
            ->post(route('network-profiles.fetch'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('fetch_batch_id', 'batch-abc-123');
    }

    public function test_fetch_without_youtube_profiles_redirects_without_batch_id(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldReceive('fetchNewItems')->once()->andReturnNull();

        $this->app->instance(NetworkProfileService::class, $service);

        $response = $this->actingAs($user)
            ->post(route('network-profiles.fetch'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionMissing('fetch_batch_id');
    }

    public function test_fetch_status_reports_inactive_when_no_batch_tracked(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('network-profiles.fetch.status'));

        $response->assertOk();
        $response->assertExactJson(['active' => false]);
    }

    public function test_fetch_status_clears_unknown_batch_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['fetch_batch_id' => 'missing-batch'])
            ->getJson(route('network-profiles.fetch.status'));

        $response->assertOk();
        $response->assertExactJson(['active' => false]);
        $response->assertSessionMissing('fetch_batch_id');
    }

    public function test_fetch_handles_exception_and_shows_alert(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldReceive('fetchNewItems')->once()->andThrow(new Exception('boom'));

        $this->app->instance(NetworkProfileService::class, $service);

        Alert::shouldReceive('error')
            ->once()
            ->with(__('messages.default.failed'), 'boom');

        $response = $this->actingAs($user)
            ->post(route('network-profiles.fetch'));

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

    #[DataProvider('storeUsernameNormalizationProvider')]
    public function test_store_normalizes_username_before_passing_to_service(string $input, string $expected): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();

        $payload = [
            'network_source_id' => $networkSource->id,
            'username' => $input,
            'is_public' => true,
            'is_favorite' => false,
        ];

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg['username'] === $expected))
            ->andReturn(NetworkProfile::factory()->make());

        $this->app->instance(NetworkProfileService::class, $service);

        $response = $this->actingAs($user)
            ->post(route('network-profiles.store'), $payload);

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_store_rejects_bare_at_username_and_does_not_call_service(): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();

        $payload = [
            'network_source_id' => $networkSource->id,
            'username' => '@',
            'is_public' => true,
            'is_favorite' => false,
        ];

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldNotReceive('create');

        $this->app->instance(NetworkProfileService::class, $service);

        $response = $this->actingAs($user)
            ->post(route('network-profiles.store'), $payload);

        $response->assertSessionHasErrors('username');
    }

    #[DataProvider('bareUrlSchemeProvider')]
    public function test_store_rejects_bare_url_scheme_and_does_not_call_service(string $username): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldNotReceive('create');

        $this->app->instance(NetworkProfileService::class, $service);

        $response = $this->actingAs($user)->post(route('network-profiles.store'), [
            'network_source_id' => $networkSource->id,
            'username' => $username,
            'is_public' => true,
            'is_favorite' => false,
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_store_rejects_non_string_username_and_does_not_call_service(): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();

        $payload = [
            'network_source_id' => $networkSource->id,
            'username' => ['unexpected', 'array'],
            'is_public' => true,
            'is_favorite' => false,
        ];

        $service = Mockery::mock(NetworkProfileService::class);
        $service->shouldNotReceive('create');

        $this->app->instance(NetworkProfileService::class, $service);

        $response = $this->actingAs($user)
            ->post(route('network-profiles.store'), $payload);

        $response->assertSessionHasErrors('username');
    }

    #[DataProvider('updateUsernameNormalizationProvider')]
    public function test_update_normalizes_username(string $input, string $expected): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();
        $networkProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'username' => 'original',
        ]);

        $payload = [
            'username' => $input,
        ];

        $response = $this->actingAs($user)
            ->put(route('network-profiles.update', ['networkProfile' => $networkProfile->id]), $payload);

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));

        $networkProfile->refresh();
        $this->assertEquals($expected, $networkProfile->username);
    }

    public function test_update_without_username_preserves_stored_value(): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();
        $networkProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'username' => 'original',
        ]);

        $response = $this->actingAs($user)
            ->put(route('network-profiles.update', ['networkProfile' => $networkProfile->id]), [
                'is_public' => true,
            ]);

        $this->assertTrue($response->isRedirect());

        $networkProfile->refresh();
        $this->assertEquals('original', $networkProfile->username);
    }

    public function test_update_rejects_bare_at_username_and_preserves_value(): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();
        $networkProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'username' => 'original',
        ]);

        $response = $this->actingAs($user)
            ->put(route('network-profiles.update', ['networkProfile' => $networkProfile->id]), [
                'username' => '@',
            ]);

        $response->assertSessionHasErrors('username');

        $networkProfile->refresh();
        $this->assertEquals('original', $networkProfile->username);
    }

    #[DataProvider('bareUrlSchemeProvider')]
    public function test_update_rejects_bare_url_scheme_and_preserves_value(string $username): void
    {
        $user = User::factory()->create();
        $networkSource = NetworkSource::factory()->create();
        $networkProfile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $networkSource->id,
            'username' => 'original',
        ]);

        $response = $this->actingAs($user)->put(
            route('network-profiles.update', ['networkProfile' => $networkProfile->id]),
            ['username' => $username]
        );

        $response->assertSessionHasErrors('username');
        $this->assertSame('original', $networkProfile->refresh()->username);
    }

    public function test_destroy_still_soft_deletes_with_inherited_prepare_for_validation(): void
    {
        $user = User::factory()->create();
        $networkProfile = NetworkProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->delete(route('network-profiles.destroy', ['networkProfile' => $networkProfile->id]));

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));
        $this->assertSoftDeleted('network_profiles', ['id' => $networkProfile->id]);
    }
}
