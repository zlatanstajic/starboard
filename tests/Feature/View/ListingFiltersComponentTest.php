<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\FilterList;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class ListingFiltersComponentTest extends TestCase
{
    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Override]
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_sources_page_renders_one_form_with_sort_search_and_apply(): void
    {
        $response = $this->get(route('network-sources.index'));

        $response->assertOk();
        $response->assertSee('action="'.route('network-sources.index').'" method="GET"', false);
        $response->assertSee('name="sort"', false);
        $response->assertSee('name="filter[search]"', false);
        $response->assertSee(__('messages.default.apply'));
        $response->assertSee(__('messages.network_source.placeholder.search'), false);
    }

    public function test_tags_page_renders_one_form_with_sort_search_and_apply(): void
    {
        $response = $this->get(route('network-tags.index'));

        $response->assertOk();
        $response->assertSee('action="'.route('network-tags.index').'" method="GET"', false);
        $response->assertSee('name="sort"', false);
        $response->assertSee('name="filter[search]"', false);
        $response->assertSee(__('messages.default.apply'));
        $response->assertSee(__('messages.network_tag.placeholder.search'), false);
    }

    public function test_listing_pages_normalize_an_array_search_value(): void
    {
        foreach (['network-sources.index', 'network-tags.index'] as $route) {
            $response = $this->get(route($route, [
                'filter' => ['search' => ['foo']],
            ]));

            $response->assertOk();
            $response->assertDontSee('value="foo"', false);
        }
    }

    public function test_sort_select_does_not_navigate_on_change_so_apply_submits_both_controls(): void
    {
        $response = $this->get(route('network-sources.index'));

        $response->assertOk();
        $response->assertDontSee('onchange="window.location.href=this.value"', false);
    }

    public function test_sort_select_submits_its_form_on_change_on_both_listing_pages(): void
    {
        foreach (['network-sources.index', 'network-tags.index'] as $route) {
            $response = $this->get(route($route));

            $response->assertOk();
            // Changing the sort applies at once instead of waiting for Apply.
            $response->assertSee('onchange="this.form.requestSubmit()"', false);
        }
    }

    public function test_profiles_filter_renders_every_range_on_both_listing_pages(): void
    {
        foreach (['network-sources.index', 'network-tags.index'] as $route) {
            $response = $this->get(route($route));

            $response->assertOk();
            $response->assertSee('name="filter[profiles]"', false);
            $response->assertSee(__('messages.default.all_profiles'));
            // "No profiles" sits between "All Profiles" and the first range.
            $response->assertSeeInOrder([
                __('messages.default.all_profiles'),
                __('messages.default.no_profiles'),
                '1-5',
                '6-10',
                '11-20',
                '21-50',
                '51-100',
                '100+',
            ], false);
        }
    }

    public function test_current_profiles_range_is_marked_selected(): void
    {
        $response = $this->get(route('network-sources.index', ['filter' => ['profiles' => '6-10']]));

        $response->assertOk();
        $response->assertSee('value="6-10" selected', false);
    }

    public function test_sources_page_renders_status_filter_after_profiles_filter(): void
    {
        $response = $this->get(route('network-sources.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'name="filter[profiles]"',
            'name="filter[exclude_from_dashboard]"',
        ], false);
        $response->assertSeeInOrder([
            __('messages.network_source.filter.all_statuses'),
            __('messages.network_source.filter.included_only'),
            __('messages.network_source.filter.excluded_only'),
        ]);
    }

    public function test_sources_status_filter_marks_the_current_status_selected(): void
    {
        $response = $this->get(route('network-sources.index', [
            'filter' => ['exclude_from_dashboard' => '1'],
        ]));

        $response->assertOk();
        $response->assertSee('value="1" selected', false);
    }

    public function test_tags_page_does_not_render_the_sources_status_filter(): void
    {
        $response = $this->get(route('network-tags.index'));

        $response->assertOk();
        $response->assertDontSee('name="filter[exclude_from_dashboard]"', false);
    }

    public function test_profiles_filter_applies_on_change_without_losing_the_search(): void
    {
        $response = $this->get(route('network-tags.index', [
            'filter' => ['profiles' => '0', 'search' => 'keepme'],
        ]));

        $response->assertOk();
        $response->assertSee('value="0" selected', false);
        // The search term stays in the form so the next submit keeps both.
        $response->assertSee('value="keepme"', false);
    }

    public function test_current_sort_option_is_marked_selected(): void
    {
        $response = $this->get(route('network-sources.index', ['sort' => '-name']));

        $response->assertOk();
        $response->assertSee('value="-name" selected', false);
    }

    public function test_search_input_echoes_the_submitted_term(): void
    {
        $response = $this->get(route('network-tags.index', ['filter' => ['search' => 'needle']]));

        $response->assertOk();
        $response->assertSee('value="needle"', false);
    }

    public function test_actions_row_holds_the_columns_and_clear_controls(): void
    {
        $response = $this->get(route('network-sources.index', [
            'sort' => '-name',
            'filter' => ['search' => 'needle'],
            'page' => 2,
        ]));

        $response->assertOk();
        $response->assertSee('data-filter-actions', false);
        $response->assertSee(__('messages.default.columns'));
        $response->assertSee(__('messages.default.clear'));
        // Clear navigates to the bare page URL: no sort, no search, no page.
        $response->assertSee("window.location.href='".route('network-sources.index')."'", false);
    }

    public function test_sources_page_uses_its_own_local_storage_keys(): void
    {
        $response = $this->get(route('network-sources.index'));

        $response->assertOk();
        $response->assertSee('show_filters_network_sources', false);
        $response->assertSee('network_sources_columns', false);
    }

    public function test_tags_page_uses_its_own_local_storage_keys(): void
    {
        $response = $this->get(route('network-tags.index'));

        $response->assertOk();
        $response->assertSee('show_filters_network_tags', false);
        $response->assertSee('network_tags_columns', false);
    }

    public function test_filter_panel_starts_closed_on_both_listing_pages(): void
    {
        foreach (['network-sources.index', 'network-tags.index'] as $route) {
            $response = $this->get(route($route));

            $response->assertOk();
            $response->assertSee('showFilters: false', false);
            $response->assertSee(__('messages.default.toggle_filters'));
        }
    }

    public function test_dashboard_keeps_its_own_filter_panel_storage_key(): void
    {
        NetworkSource::factory()->create(['user_id' => $this->user->id]);
        NetworkTag::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee("localStorage.setItem('show_filters',", false);
        $response->assertSee('dashboard_columns', false);
    }

    public function test_filter_lists_page_uses_its_status_filter_without_the_profiles_filter(): void
    {
        FilterList::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('filter-lists.index', [
            'filter' => ['is_published' => '1'],
        ]));

        $response->assertOk();
        $response->assertSee('name="filter[is_published]"', false);
        $response->assertSee('value="1" selected', false);
        $response->assertDontSee('name="filter[profiles]"', false);
        $response->assertSee('filter_lists_columns', false);
        $response->assertSee('show_filters_filter_lists', false);
    }

    public function test_existing_listing_component_defaults_are_unchanged(): void
    {
        foreach (['network-sources.index', 'network-tags.index'] as $route) {
            $response = $this->get(route($route));

            $response->assertOk();
            $response->assertSee('name="filter[profiles]"', false);
        }

        $this->get(route('network-sources.index'))
            ->assertSee('name="filter[exclude_from_dashboard]"', false);
    }
}
