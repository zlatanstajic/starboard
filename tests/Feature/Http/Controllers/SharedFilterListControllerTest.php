<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\FilterList;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Override;
use Tests\TestCase;

class SharedFilterListControllerTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    #[Override]
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_guest_sees_only_public_profiles_owned_by_the_list_owner(): void
    {
        [$list, $ownerSource] = $this->createListContext();
        $ownerProfile = NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $ownerSource->id,
            'username' => 'owner_visible_profile',
            'is_public' => true,
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $ownerSource->id,
            'username' => 'owner_private_profile',
            'is_public' => false,
        ]);
        $other = $this->createUserWithoutEvents();
        $otherSource = NetworkSource::factory()->create(['user_id' => $other->id, 'name' => 'Other owner source']);
        NetworkProfile::factory()->create([
            'user_id' => $other->id,
            'network_source_id' => $otherSource->id,
            'username' => 'other_owner_profile',
            'is_public' => true,
        ]);

        $response = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'include' => 'user',
        ]));

        $response->assertOk();
        $response->assertViewIs('shared-list');
        $response->assertSee($ownerProfile->username);
        $response->assertDontSee('owner_private_profile');
        $response->assertDontSee('other_owner_profile');
        $response->assertDontSee('Other owner source');
        $response->assertDontSee('increment-visit-link', false);
        $response->assertSee('data-shared-list-filters', false);
        $response->assertSee('action="'.route('filter-lists.show', $list->hash).'" method="GET"', false);
        $this->assertFalse($response->viewData('networkProfiles')->first()->relationLoaded('user'));
    }

    public function test_authenticated_non_owner_sees_the_same_owner_profiles(): void
    {
        [$list, $source] = $this->createListContext();
        $source->update(['url' => 'https://example.test/{username}']);
        $tag = NetworkTag::factory()->create(['user_id' => $list->user_id, 'name' => 'Owner tag']);
        $profile = NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $source->id,
            'username' => 'shared_with_non_owner',
            'is_public' => true,
        ]);
        $profile->networkTags()->attach($tag);
        $this->actingAs($this->createUserWithoutEvents());

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk();
        $response->assertSee('shared_with_non_owner');
        $response->assertSee($source->name);
        $response->assertSee($tag->name);
        $response->assertSee('href="https://example.test/shared_with_non_owner"', false);
        $this->assertGreaterThanOrEqual(2, Str::substrCount($response->getContent(), $tag->name));
    }

    public function test_public_list_page_carries_seo_metadata_and_the_list_icon(): void
    {
        [$list] = $this->createListContext(['description' => 'Handpicked tech creators worth following.']);
        $expectedTitle = $list->name.' - '.config('app.name').' '.__('messages.filter_list.public.lists');

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk();
        $response->assertSee('<title>'.$expectedTitle.'</title>', false);
        $response->assertSee('<meta name="description" content="Handpicked tech creators worth following.">', false);
        $response->assertSee('<link rel="canonical" href="'.$list->publicUrl().'">', false);
        $response->assertSee('content="index, follow, max-image-preview:large"', false);
        $response->assertSee('<meta property="og:url" content="'.$list->publicUrl().'">', false);
        $response->assertSee('<meta property="og:title" content="'.$expectedTitle.'">', false);
        $response->assertSee('<meta name="twitter:card" content="summary">', false);
        $response->assertSee('aria-label="'.__('messages.filter_list.public.lists').'"', false);
    }

    public function test_public_list_page_advertises_the_favicon_and_the_logo_as_its_social_image(): void
    {
        [$list] = $this->createListContext();
        $appName = (string) config('app.name');

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk();
        $response->assertSee('<link rel="icon" href="'.asset('favicon.ico').'" type="image/x-icon">', false);
        $response->assertSee('<link rel="shortcut icon" href="'.asset('logo.png').'" type="image/x-icon">', false);
        $response->assertSee('<link rel="apple-touch-icon" href="'.asset('logo.png').'">', false);
        $response->assertSee('<meta property="og:image" content="'.asset('logo.png').'">', false);
        $response->assertSee('<meta property="og:image:type" content="image/png">', false);
        $response->assertSee('<meta property="og:image:width" content="800">', false);
        $response->assertSee('<meta property="og:image:height" content="800">', false);
        $response->assertSee('<meta property="og:image:alt" content="'.e($appName).'">', false);
        $response->assertSee('<meta name="twitter:image" content="'.asset('logo.png').'">', false);
        $response->assertSee('<meta name="twitter:image:alt" content="'.e($appName).'">', false);
    }

    /**
     * The social image dimensions are literals in the layout, so they only
     * stay truthful while they match the logo actually shipped in public/.
     */
    public function test_declared_social_image_dimensions_match_the_shipped_logo(): void
    {
        $size = getimagesize(public_path('logo.png'));

        $this->assertIsArray($size);
        $this->assertSame(800, $size[0]);
        $this->assertSame(800, $size[1]);
        $this->assertSame('image/png', $size['mime']);
    }

    public function test_a_list_without_a_description_falls_back_to_the_default_meta_description(): void
    {
        [$list] = $this->createListContext(['description' => null]);

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk();
        $response->assertSee(
            '<meta name="description" content="'.e(__('messages.filter_list.public.default_description')).'">',
            false
        );
    }

    public function test_filter_dropdowns_hide_sources_and_tags_absent_from_the_list(): void
    {
        $owner = $this->createUserWithoutEvents();
        $shownSource = NetworkSource::factory()->create(['user_id' => $owner->id, 'name' => 'Shown source']);
        $unusedSource = NetworkSource::factory()->create(['user_id' => $owner->id, 'name' => 'Secret unused source']);
        $shownTag = NetworkTag::factory()->create(['user_id' => $owner->id, 'name' => 'Shown tag']);
        $unusedTag = NetworkTag::factory()->create(['user_id' => $owner->id, 'name' => 'Secret unused tag']);
        $privateTag = NetworkTag::factory()->create(['user_id' => $owner->id, 'name' => 'Secret private tag']);
        $shown = NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $shownSource->id,
            'username' => 'shown_profile',
            'is_public' => true,
        ]);
        $shown->networkTags()->attach($shownTag);
        // A private profile must not surface its source or tags either.
        $hidden = NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $unusedSource->id,
            'username' => 'hidden_profile',
            'is_public' => false,
        ]);
        $hidden->networkTags()->attach($privateTag);
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'filters' => ['filter' => [], 'sort' => 'username'],
        ]);

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk();
        $response->assertSee('Shown source');
        $response->assertSee('Shown tag');
        $response->assertDontSee('Secret unused source');
        $response->assertDontSee('Secret unused tag');
        $response->assertDontSee('Secret private tag');
        $this->assertSame(['Shown source'], $response->viewData('networkSources')->pluck('name')->all());
        $this->assertSame(['Shown tag'], $response->viewData('networkTags')->pluck('name')->all());
        $this->assertDatabaseHas('network_tags', ['id' => $unusedTag->id, 'user_id' => $owner->id]);
    }

    public function test_saved_filters_narrow_the_dropdowns_to_the_captured_subset(): void
    {
        $owner = $this->createUserWithoutEvents();
        $savedSource = NetworkSource::factory()->create(['user_id' => $owner->id, 'name' => 'Captured source']);
        $otherSource = NetworkSource::factory()->create(['user_id' => $owner->id, 'name' => 'Filtered out source']);
        $savedTag = NetworkTag::factory()->create(['user_id' => $owner->id, 'name' => 'Captured tag']);
        $otherTag = NetworkTag::factory()->create(['user_id' => $owner->id, 'name' => 'Filtered out tag']);
        $inside = NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $savedSource->id,
            'username' => 'inside_capture',
            'is_public' => true,
        ]);
        $inside->networkTags()->attach($savedTag);
        $outside = NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $otherSource->id,
            'username' => 'outside_capture',
            'is_public' => true,
        ]);
        $outside->networkTags()->attach($otherTag);
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'filters' => ['filter' => ['network_source_id' => $savedSource->id], 'sort' => 'username'],
        ]);

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk();
        $this->assertSame(['Captured source'], $response->viewData('networkSources')->pluck('name')->all());
        $this->assertSame(['Captured tag'], $response->viewData('networkTags')->pluck('name')->all());
        $response->assertDontSee('Filtered out source');
        $response->assertDontSee('Filtered out tag');
    }

    public function test_dropdowns_never_include_another_users_sources_or_tags(): void
    {
        [$list, $source] = $this->createListContext();
        NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $source->id,
            'username' => 'owner_profile',
            'is_public' => true,
        ]);
        $stranger = $this->createUserWithoutEvents();
        NetworkSource::factory()->create(['user_id' => $stranger->id, 'name' => 'Stranger source']);
        NetworkTag::factory()->create(['user_id' => $stranger->id, 'name' => 'Stranger tag']);

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk();
        $response->assertDontSee('Stranger source');
        $response->assertDontSee('Stranger tag');
    }

    public function test_sort_dropdown_offers_only_name_and_username(): void
    {
        [$list] = $this->createListContext();

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk();
        $response->assertSee(__('messages.filter_list.public.sort.name'));
        $response->assertSee(__('messages.filter_list.public.sort.-name'));
        $response->assertSee(__('messages.filter_list.public.sort.username'));
        $response->assertSee(__('messages.filter_list.public.sort.-username'));
        $response->assertSee('value="name"', false);
        $response->assertDontSee('value="number_of_visits"', false);
        $response->assertDontSee('value="-number_of_visits"', false);
        $response->assertDontSee('value="last_visit_at"', false);
        $response->assertDontSee('value="created_at"', false);
        $response->assertDontSee('value="updated_at"', false);
    }

    public function test_name_sort_uses_the_title_and_falls_back_to_the_username(): void
    {
        $owner = $this->createUserWithoutEvents();
        $source = NetworkSource::factory()->create(['user_id' => $owner->id]);
        // Titles and usernames are deliberately in opposite orders, so only a
        // title-based sort can produce the expected sequence.
        NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $source->id,
            'username' => 'zzz_has_a_title',
            'title' => 'Aardvark Channel',
            'is_public' => true,
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $source->id,
            'username' => 'mmm_untitled_profile',
            'title' => null,
            'is_public' => true,
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $source->id,
            'username' => 'aaa_zebra_title',
            'title' => 'Zebra Channel',
            'is_public' => true,
        ]);
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'filters' => ['filter' => [], 'sort' => 'username'],
        ]);

        $ascending = $this->get(route('filter-lists.show', ['token' => $list->hash, 'sort' => 'name']));
        $descending = $this->get(route('filter-lists.show', ['token' => $list->hash, 'sort' => '-name']));

        $ascending->assertOk()->assertSeeInOrder([
            'Aardvark Channel',
            'mmm_untitled_profile',
            'Zebra Channel',
        ]);
        $descending->assertOk()->assertSeeInOrder([
            'Zebra Channel',
            'mmm_untitled_profile',
            'Aardvark Channel',
        ]);
    }

    public function test_visitor_cannot_address_a_sort_that_was_removed_from_the_dropdown(): void
    {
        [$list] = $this->createListContext();

        $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'sort' => '-number_of_visits',
        ]))->assertBadRequest();
    }

    public function test_a_saved_sort_outside_the_public_set_still_orders_the_listing(): void
    {
        $owner = $this->createUserWithoutEvents();
        $source = NetworkSource::factory()->create(['user_id' => $owner->id]);
        NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $source->id,
            'username' => 'fewer_visits',
            'number_of_visits' => 1,
            'is_public' => true,
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $source->id,
            'username' => 'more_visits',
            'number_of_visits' => 99,
            'is_public' => true,
        ]);
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'filters' => ['filter' => [], 'sort' => '-number_of_visits'],
        ]);

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk()->assertSeeInOrder(['more_visits', 'fewer_visits']);
    }

    public function test_a_saved_name_sort_does_not_collide_with_the_public_name_sort(): void
    {
        $owner = $this->createUserWithoutEvents();
        $source = NetworkSource::factory()->create(['user_id' => $owner->id]);
        NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $source->id,
            'username' => 'bbb_second',
            'title' => null,
            'is_public' => true,
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $source->id,
            'username' => 'aaa_first',
            'title' => null,
            'is_public' => true,
        ]);
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'filters' => ['filter' => [], 'sort' => 'name'],
        ]);

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk()->assertSeeInOrder(['aaa_first', 'bbb_second']);
    }

    public function test_unknown_unpublished_and_soft_deleted_tokens_all_return_not_found(): void
    {
        $owner = $this->createUserWithoutEvents();
        $unpublished = FilterList::factory()->create(['user_id' => $owner->id, 'is_published' => false]);
        $deleted = FilterList::factory()->create(['user_id' => $owner->id]);
        $deleted->delete();

        $this->get(route('filter-lists.show', 'UnknownHash12'))->assertNotFound();
        $this->get(route('filter-lists.show', $unpublished->hash))->assertNotFound();
        $this->get(route('filter-lists.show', $deleted->hash))->assertNotFound();
    }

    public function test_clear_button_sits_after_apply_and_drops_only_the_visitor_query(): void
    {
        [$list] = $this->createListContext();

        $response = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'filter' => ['search' => 'needle'],
            'sort' => '-username',
        ]));

        $response->assertOk();
        $response->assertSeeInOrder([
            __('messages.default.apply'),
            'data-clear-shared-filters',
            __('messages.default.clear'),
        ], false);
        $response->assertSee('href="'.route('filter-lists.show', $list->hash).'" data-clear-shared-filters', false);
        $response->assertDontSee('data-clear-shared-filters" href', false);
    }

    public function test_filter_panel_starts_collapsed_behind_its_own_storage_key(): void
    {
        [$list] = $this->createListContext();

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk();
        $response->assertSee('showFilters: false', false);
        $response->assertSee("localStorage.getItem('show_filters_shared_list')", false);
        $response->assertSee("localStorage.setItem('show_filters_shared_list'", false);
        $response->assertSee('x-show="showFilters"', false);
        // The authenticated Filter Lists page keeps its own key.
        $response->assertDontSee('show_filters_filter_lists', false);
    }

    public function test_the_toggle_sits_in_the_header_above_the_filter_form(): void
    {
        [$list] = $this->createListContext();

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk();
        $response->assertSeeInOrder([
            $list->name,
            'data-filter-toggle',
            'data-shared-list-filters',
        ], false);
    }

    public function test_only_the_bare_hash_token_resolves_the_list(): void
    {
        [$list] = $this->createListContext();

        $this->get(route('filter-lists.show', $list->hash))->assertOk()->assertSee($list->name);
        $this->get('/lists/my-shared-list-'.$list->hash)->assertNotFound();
    }

    public function test_visitor_filters_narrow_the_saved_set_and_sort_overrides_saved_sort(): void
    {
        [$list, $firstSource] = $this->createListContext([
            'filters' => ['filter' => [], 'sort' => 'username'],
        ]);
        $secondSource = NetworkSource::factory()->create(['user_id' => $list->user_id]);
        $tag = NetworkTag::factory()->create(['user_id' => $list->user_id, 'name' => 'Chosen tag']);
        $first = NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $firstSource->id,
            'username' => 'aaa_first_profile',
            'is_public' => true,
        ]);
        $second = NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $secondSource->id,
            'username' => 'zzz_second_profile',
            'is_public' => true,
        ]);
        $second->networkTags()->attach($tag);

        $filtered = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'filter' => ['network_source_id' => $secondSource->id],
        ]));
        $sorted = $this->get(route('filter-lists.show', ['token' => $list->hash, 'sort' => '-username']));
        $tagged = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'filter' => ['tags' => $tag->id],
        ]));
        $searched = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'filter' => ['search' => 'aaa_first'],
        ]));

        $filtered->assertOk()->assertSee('zzz_second_profile')->assertDontSee('aaa_first_profile');
        $sorted->assertOk()->assertSeeInOrder(['zzz_second_profile', 'aaa_first_profile']);
        $tagged->assertOk()->assertSee($second->username)->assertDontSee($first->username);
        $searched->assertOk()->assertSee($first->username)->assertDontSee($second->username);
    }

    public function test_saved_filters_are_replayed_before_visitor_filters(): void
    {
        $owner = $this->createUserWithoutEvents();
        $savedSource = NetworkSource::factory()->create(['user_id' => $owner->id]);
        $otherSource = NetworkSource::factory()->create(['user_id' => $owner->id]);
        $savedProfile = NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $savedSource->id,
            'username' => 'saved_filter_profile',
            'is_public' => true,
        ]);
        $otherProfile = NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $otherSource->id,
            'username' => 'outside_saved_filter',
            'is_public' => true,
        ]);
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'filters' => ['filter' => ['network_source_id' => $savedSource->id], 'sort' => 'username'],
        ]);

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk()->assertSee($savedProfile->username)->assertDontSee($otherProfile->username);
    }

    public function test_visitor_cannot_address_is_public_to_widen_the_set(): void
    {
        [$list, $source] = $this->createListContext();
        NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $source->id,
            'username' => 'private_cannot_be_requested',
            'is_public' => false,
        ]);

        $response = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'filter' => ['is_public' => '0'],
        ]));

        $response->assertBadRequest();
        $this->assertDatabaseHas('network_profiles', [
            'user_id' => $list->user_id,
            'username' => 'private_cannot_be_requested',
            'is_public' => false,
        ]);
    }

    public function test_a_non_string_saved_sort_falls_back_instead_of_erroring(): void
    {
        [$list, $source] = $this->createListContext([
            'filters' => ['filter' => [], 'sort' => ['username', '-username']],
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $source->id,
            'username' => 'survives_a_broken_saved_sort',
            'is_public' => true,
        ]);

        $response = $this->get(route('filter-lists.show', $list->hash));

        $response->assertOk()->assertSee('survives_a_broken_saved_sort');
    }

    public function test_a_tag_the_list_does_not_expose_is_not_a_boolean_oracle(): void
    {
        [$list, $source] = $this->createListContext();
        $exposedTag = NetworkTag::factory()->create(['user_id' => $list->user_id, 'name' => 'Exposed tag']);
        $shown = NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $source->id,
            'username' => 'exposed_profile',
            'is_public' => true,
        ]);
        $shown->networkTags()->attach($exposedTag);
        // A stranger's tag, attached to one of the owner's private profiles.
        $stranger = $this->createUserWithoutEvents();
        $strangerTag = NetworkTag::factory()->create(['user_id' => $stranger->id, 'name' => 'Stranger tag']);
        $private = NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $source->id,
            'username' => 'private_profile',
            'is_public' => false,
        ]);
        $private->networkTags()->attach($strangerTag);

        $exposed = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'filter' => ['tags' => $exposedTag->id],
        ]));
        $foreign = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'filter' => ['tags' => $strangerTag->id],
        ]));
        $mixed = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'filter' => ['tags' => [$exposedTag->id, $strangerTag->id]],
        ]));

        $exposed->assertOk()->assertSee('exposed_profile');
        // The foreign id is dropped and the filter matches nothing, so the
        // response cannot distinguish "tag exists on a profile" from "it does not".
        $foreign->assertOk()->assertDontSee('exposed_profile');
        $foreign->assertDontSee('private_profile');
        $this->assertSame(0, $foreign->viewData('networkProfiles')->total());
        // A mixed request keeps only the exposed id rather than failing closed.
        $mixed->assertOk()->assertSee('exposed_profile');
    }

    public function test_the_any_and_none_tag_sentinels_partition_the_exposed_profiles(): void
    {
        [$list, $source] = $this->createListContext();
        $tag = NetworkTag::factory()->create(['user_id' => $list->user_id, 'name' => 'Exposed tag']);
        $tagged = NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $source->id,
            'username' => 'tagged_profile',
            'is_public' => true,
        ]);
        $tagged->networkTags()->attach($tag);
        NetworkProfile::factory()->create([
            'user_id' => $list->user_id,
            'network_source_id' => $source->id,
            'username' => 'plain_profile',
            'is_public' => true,
        ]);

        $any = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'filter' => ['tags' => 'any'],
        ]));
        $none = $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'filter' => ['tags' => 'none'],
        ]));

        $any->assertOk()->assertSee('tagged_profile')->assertDontSee('plain_profile');
        $this->assertSame(1, $any->viewData('networkProfiles')->total());
        $none->assertOk()->assertSee('plain_profile')->assertDontSee('tagged_profile');
        $this->assertSame(1, $none->viewData('networkProfiles')->total());
    }

    public function test_a_saved_sort_outside_the_public_set_stays_unaddressable(): void
    {
        $owner = $this->createUserWithoutEvents();
        $source = NetworkSource::factory()->create(['user_id' => $owner->id]);
        NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $source->id,
            'username' => 'fewer_visits',
            'number_of_visits' => 1,
            'is_public' => true,
        ]);
        NetworkProfile::factory()->create([
            'user_id' => $owner->id,
            'network_source_id' => $source->id,
            'username' => 'more_visits',
            'number_of_visits' => 99,
            'is_public' => true,
        ]);
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'filters' => ['filter' => [], 'sort' => '-number_of_visits'],
        ]);

        // The saved sort still orders the listing by default ...
        $this->get(route('filter-lists.show', $list->hash))
            ->assertOk()
            ->assertSeeInOrder(['more_visits', 'fewer_visits']);

        // ... but a visitor cannot address it, in either direction.
        $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'sort' => '-number_of_visits',
        ]))->assertBadRequest();
        $this->get(route('filter-lists.show', [
            'token' => $list->hash,
            'sort' => 'number_of_visits',
        ]))->assertBadRequest();
    }

    public function test_the_public_list_page_is_rate_limited(): void
    {
        [$list] = $this->createListContext();

        $this->assertContains(
            'throttle:60,1',
            resolve(Router::class)->getRoutes()->getByName('filter-lists.show')->gatherMiddleware()
        );
        $this->get(route('filter-lists.show', $list->hash))->assertOk();
    }

    /** @return array{FilterList, NetworkSource} */
    private function createListContext(array $listAttributes = []): array
    {
        $owner = $this->createUserWithoutEvents();
        $source = NetworkSource::factory()->create(['user_id' => $owner->id]);
        $list = FilterList::factory()->create(array_merge([
            'user_id' => $owner->id,
            'name' => 'Public test list',
            'filters' => ['filter' => [], 'sort' => 'username'],
        ], $listAttributes));

        return [$list, $source];
    }

    private function createUserWithoutEvents(): User
    {
        return User::withoutEvents(fn (): User => User::factory()->create());
    }
}
