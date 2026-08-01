<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\FetchYouTubeNewItemsJob;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use App\Models\YouTubeFetchBatch;
use App\Models\YouTubeFetchDailyBudget;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Bus;
use Override;
use Tests\TestCase;

class FetchNewItemsTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        config()->set('youtube.execution_enabled', true);
        YouTubeFetchDailyBudget::query()->delete();
    }

    public function test_fetch_route_dispatches_a_job_for_each_youtube_profile(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $youtube = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'url' => 'https://youtube.com/@{username}/videos',
        ]);
        $instagram = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'url' => 'https://instagram.com/{username}',
        ]);

        NetworkProfile::factory()->count(2)->create([
            'user_id' => $user->id,
            'network_source_id' => $youtube->id,
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $instagram->id,
        ]);

        $response = $this->actingAs($user)->post(route('network-profiles.fetch'));

        $response->assertRedirect(route('dashboard'));
        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2
            && $batch->jobs->every(fn ($job): bool => $job instanceof FetchYouTubeNewItemsJob));
    }

    public function test_fetch_route_refuses_requests_when_execution_is_disabled(): void
    {
        config()->set('youtube.execution_enabled', false);
        Bus::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('network-profiles.fetch'))
            ->assertServiceUnavailable()
            ->assertSee(__('messages.network_profile.fetch.disabled'));

        Bus::assertNothingBatched();
    }

    public function test_dashboard_control_requires_both_feature_flags(): void
    {
        $user = User::factory()->create();

        config()->set('youtube.ui_enabled', true);
        $this->actingAs($user)->get(route('dashboard'))->assertSee('aria-live="polite"', false);

        config()->set('youtube.execution_enabled', false);
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('aria-live="polite"', false);
    }

    public function test_dashboard_disables_fetch_when_shared_circuit_is_open(): void
    {
        $user = User::factory()->create();
        config()->set('youtube.ui_enabled', true);
        YouTubeFetchDailyBudget::query()->create([
            'budget_date' => now()->utc()->toDateString(),
            'blocked_until' => now()->addHour(),
            'block_reason' => 'consent_required',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('disabled', false)
            ->assertSee(__('messages.network_profile.fetch.circuit_open'));
    }

    public function test_repeated_dispatch_reconnects_to_one_active_user_batch(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'url' => 'https://youtube.com/@{username}/videos',
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
        ]);

        $this->actingAs($user)->post(route('network-profiles.fetch'))->assertRedirect();
        $this->actingAs($user)->post(route('network-profiles.fetch'))->assertRedirect();

        Bus::assertBatchCount(1);
        $this->assertSame(1, YouTubeFetchBatch::query()->where('user_id', $user->id)->count());
    }

    public function test_new_items_filter_returns_only_profiles_with_new_items(): void
    {
        $user = User::factory()->create();
        $source = NetworkSource::factory()->create(['user_id' => $user->id]);

        $withNew = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'username' => 'has_new_items',
            'new_items' => 4,
            // Oldest last_visit_at so it sorts first on page 1 (defaultSort is
            // last_visit_at ASC) regardless of leftover data on the persistent DB.
            'last_visit_at' => now()->subYears(20),
        ]);
        $withoutNew = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $source->id,
            'username' => 'no_new_items',
            'new_items' => 0,
            // Equally old so it would land on page 1 if the filter were a no-op,
            // making the exclusion assertion meaningful.
            'last_visit_at' => now()->subYears(20),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', ['filter' => ['new_items' => '1']]));

        $response->assertOk();
        $profiles = $response->viewData('networkProfiles');
        $usernames = $profiles->pluck('username')->toArray();

        $this->assertContains($withNew->username, $usernames);
        $this->assertNotContains($withoutNew->username, $usernames);
    }

    public function test_fetch_always_dispatches_jobs_for_matching_profiles_only(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $youtube = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'url' => 'https://youtube.com/@{username}/videos',
        ]);

        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $youtube->id,
            'username' => 'matching_'.$this->timestamp,
            'new_items' => 3,
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $youtube->id,
            'username' => 'non_matching_'.$this->timestamp,
            'new_items' => 0,
        ]);

        // No "only_filtered" input is submitted; filtered fetching is now the
        // unconditional behavior, so only the matching profile is dispatched.
        $response = $this->actingAs($user)->post(
            route('network-profiles.fetch', ['filter' => ['new_items' => '1']])
        );

        $response->assertRedirect(route('dashboard', ['filter' => ['new_items' => '1']]));
        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1);
    }

    public function test_fetch_redirect_preserves_current_query_string(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $youtube = NetworkSource::factory()->create([
            'user_id' => $user->id,
            'url' => 'https://youtube.com/@{username}/videos',
        ]);

        NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'network_source_id' => $youtube->id,
            'username' => 'preserve_'.$this->timestamp,
            'new_items' => 5,
        ]);

        $query = ['filter' => ['new_items' => '1'], 'sort' => 'username'];

        $response = $this->actingAs($user)->post(
            route('network-profiles.fetch', $query)
        );

        $response->assertRedirect(route('dashboard', $query));
    }

    public function test_visiting_a_profile_resets_new_items_to_zero(): void
    {
        $user = User::factory()->create();
        $profile = NetworkProfile::factory()->create([
            'user_id' => $user->id,
            'new_items' => 7,
            'number_of_visits' => 3,
        ]);

        $response = $this->actingAs($user)
            ->post(route('network-profiles.recordVisit', ['networkProfile' => $profile->id]));

        $response->assertRedirect(route('dashboard'));

        $profile->refresh();
        $this->assertSame(0, $profile->new_items);
        $this->assertSame(4, $profile->number_of_visits);
    }
}
