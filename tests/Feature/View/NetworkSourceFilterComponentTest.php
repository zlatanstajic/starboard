<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\NetworkSourcesEnum;
use App\Models\NetworkSource;
use App\Models\User;
use Tests\TestCase;

class NetworkSourceFilterComponentTest extends TestCase
{
    public function test_renders_brand_icon_for_every_network_source(): void
    {
        $user = User::factory()->create();
        $this->createSource($user, 'youtube_filter', 'https://youtube.com/@{username}');
        $this->createSource($user, 'instagram_filter', 'https://instagram.com/{username}');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-network-source-filter', false);
        $response->assertSee('role="listbox"', false);
        $response->assertSee(NetworkSourcesEnum::YouTube->brandIconPath(), false);
        $response->assertSee(NetworkSourcesEnum::Instagram->brandIconPath(), false);
    }

    public function test_renders_fallback_icon_for_source_without_recognized_brand(): void
    {
        $user = User::factory()->create();
        $this->createSource($user, 'unknown_filter', 'https://unknown-platform.test/{username}');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('fill="currentColor"', false);
    }

    public function test_marks_all_sources_option_selected_when_no_source_filter_applied(): void
    {
        $user = User::factory()->create();
        $source = $this->createSource($user, 'youtube_filter', 'https://youtube.com/@{username}');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        // The "all sources" option is the selected one, so the source option is idle.
        $response->assertSeeInOrder([
            'aria-selected="true"',
            __('messages.network_profile.filter.all_network_sources'),
            'aria-selected="false"',
            ucfirst($source->name),
        ], false);
    }

    public function test_marks_filtered_source_as_selected_and_shows_it_on_the_trigger(): void
    {
        $user = User::factory()->create();
        $source = $this->createSource($user, 'youtube_filter', 'https://youtube.com/@{username}');

        $response = $this->actingAs($user)->get(
            route('dashboard', ['filter' => ['network_source_id' => $source->id]])
        );

        $response->assertOk();

        // Trigger shows the selected source (name + brand icon) instead of the "all sources" label,
        // and the matching option is flagged selected while "all sources" is not.
        $response->assertSeeInOrder([
            'aria-haspopup="listbox"',
            NetworkSourcesEnum::YouTube->brandColor(),
            ucfirst($source->name),
            'aria-selected="false"',
            __('messages.network_profile.filter.all_network_sources'),
            'aria-selected="true"',
        ], false);
    }

    public function test_option_links_carry_the_source_filter_and_preserve_other_filters(): void
    {
        $user = User::factory()->create();
        $source = $this->createSource($user, 'youtube_filter', 'https://youtube.com/@{username}');

        $response = $this->actingAs($user)->get(
            route('dashboard', ['filter' => ['is_public' => '1']])
        );

        $response->assertOk();
        $response->assertSee('filter%5Bnetwork_source_id%5D='.$source->id, false);
        $response->assertSee('filter%5Bis_public%5D=1', false);
    }

    public function test_ignores_a_non_scalar_source_filter_instead_of_failing(): void
    {
        $user = User::factory()->create();
        $this->createSource($user, 'youtube_filter', 'https://youtube.com/@{username}');

        $response = $this->actingAs($user)->get(
            route('dashboard', ['filter' => ['network_source_id' => ['1', '2']]])
        );

        // Falls back to the "all sources" selection rather than casting an array to int.
        $response->assertOk();
        $response->assertSeeInOrder([
            'aria-selected="true"',
            __('messages.network_profile.filter.all_network_sources'),
            'aria-selected="false"',
        ], false);
    }

    public function test_trigger_keeps_the_metrics_of_the_sibling_native_filters(): void
    {
        $user = User::factory()->create();
        $this->createSource($user, 'youtube_filter', 'https://youtube.com/@{username}');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        // Height/width parity with the neighbouring native selects (text-sm + p-2.5 + border + w-full)
        // is what keeps the filter row on a single line — pin it so restyling cannot silently break it.
        $response->assertSee('text-sm rounded-lg flex w-full items-center gap-2 p-2.5', false);
    }

    public function test_filter_is_twice_as_wide_as_the_other_filters_in_its_row(): void
    {
        $user = User::factory()->create();
        $this->createSource($user, 'youtube_filter', 'https://youtube.com/@{username}');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        // The panel inherits the trigger width, so the wrapper is what stops long source
        // names being truncated once the dropdown is expanded.
        $response->assertSee('<div class="w-full md:w-1/3">', false);
    }

    /**
     * Creates a network source for the user with a deterministic brand URL.
     */
    private function createSource(User $user, string $namePrefix, string $url): NetworkSource
    {
        return NetworkSource::query()->create([
            'user_id' => $user->id,
            'name' => $namePrefix.'_'.uniqid(),
            'url' => $url,
        ]);
    }
}
