<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\FetchYouTubeNewItemsJob;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Bus;
use Override;
use Tests\TestCase;

class FetchNewItemsTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_fetch_button_renders_left_of_refresh_button(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $content = $response->getContent();

        $fetchPos = strpos($content, route('network-profiles.fetch'));
        $refreshPos = strpos($content, 'window.location.href=window.location.href');

        $this->assertNotFalse($fetchPos, 'Fetch form action not found on dashboard.');
        $this->assertNotFalse($refreshPos, 'Refresh button not found on dashboard.');
        $this->assertLessThan($refreshPos, $fetchPos, 'Fetch button should appear before (left of) Refresh.');
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
