<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\FilterList;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;
use App\Services\FilterListService;
use Exception;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class FilterListControllerTest extends TestCase
{
    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        DB::beginTransaction();
        $this->user = User::withoutEvents(fn (): User => User::factory()->create());
        $this->actingAs($this->user);
    }

    #[Override]
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_index_lists_only_the_authenticated_users_filter_lists(): void
    {
        $owned = FilterList::factory()->create(['user_id' => $this->user->id, 'name' => 'Owned list']);
        $otherOwner = User::withoutEvents(fn (): User => User::factory()->create());
        FilterList::factory()->create(['user_id' => $otherOwner->id, 'name' => 'Another owner list']);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk()->assertViewIs('filter-lists')->assertViewHas('filterLists');
        $response->assertSee($owned->name)->assertDontSee('Another owner list');
    }

    public function test_index_renders_the_name_as_a_public_link_without_a_url_column(): void
    {
        $list = FilterList::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Linked list',
        ]);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk();
        $response->assertSee(
            '<a href="'.$list->publicUrl().'" target="_blank" rel="noopener noreferrer" title="'.$list->publicUrl().'"',
            false
        );
        $response->assertSee('Linked list');
        $response->assertDontSee('data-col="url"', false);
        $response->assertDontSee(__('messages.default.url'));
    }

    public function test_index_renders_the_filters_column_after_description_with_resolved_names(): void
    {
        $source = NetworkSource::factory()->create(['user_id' => $this->user->id, 'name' => 'Owned source']);
        $tag = NetworkTag::factory()->create(['user_id' => $this->user->id, 'name' => 'Owned tag']);
        FilterList::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Described list',
            'filters' => [
                'filter' => ['network_source_id' => (string) $source->id, 'tags' => [(string) $tag->id]],
                'sort' => '-username',
            ],
        ]);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'data-col="description"',
            'data-col="filters"',
            'data-col="timestamps"',
        ], false);
        $response->assertSee('Owned source');
        $response->assertSee('Owned tag');
        $response->assertSee(__('messages.network_profile.sort.-username'));
    }

    public function test_index_shows_a_placeholder_when_a_capture_has_no_filters(): void
    {
        FilterList::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Empty capture list',
            'filters' => [],
        ]);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk();
        $response->assertSee('Empty capture list');
        $response->assertSee(__('messages.filter_list.filters'));
    }

    public function test_index_does_not_resolve_names_owned_by_another_user(): void
    {
        $otherOwner = User::withoutEvents(fn (): User => User::factory()->create());
        $foreignSource = NetworkSource::factory()->create([
            'user_id' => $otherOwner->id,
            'name' => 'Foreign source name',
        ]);
        FilterList::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Cross owner list',
            'filters' => ['filter' => ['network_source_id' => (string) $foreignSource->id]],
        ]);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk();
        $response->assertDontSee('Foreign source name');
        $response->assertSee((string) $foreignSource->id);
    }

    public function test_index_provides_an_apply_url_only_for_the_owners_own_lists(): void
    {
        $owned = FilterList::factory()->create([
            'user_id' => $this->user->id,
            'filters' => ['filter' => ['search' => 'needle'], 'sort' => '-username'],
        ]);
        $otherOwner = User::withoutEvents(fn (): User => User::factory()->create());
        $foreign = FilterList::factory()->create(['user_id' => $otherOwner->id]);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk();
        $applyUrls = $response->viewData('applyUrls');
        $this->assertSame([$owned->id], array_keys($applyUrls));
        $this->assertArrayNotHasKey($foreign->id, $applyUrls);
        $this->assertStringStartsWith(route('dashboard').'?', $applyUrls[$owned->id]);
        parse_str((string) parse_url($applyUrls[$owned->id], PHP_URL_QUERY), $query);
        $this->assertSame(
            ['filter' => ['search' => 'needle'], 'sort' => '-username'],
            $query
        );
    }

    public function test_index_renders_the_apply_action_in_the_actions_cell(): void
    {
        $list = FilterList::factory()->create([
            'user_id' => $this->user->id,
            'filters' => ['filter' => ['search' => 'needle']],
        ]);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk();
        $applyUrl = $response->viewData('applyUrls')[$list->id];
        $response->assertSee('data-apply-filter-list', false);
        $response->assertSee('href="'.e($applyUrl).'"', false);
        $response->assertSee(__('messages.filter_list.apply_title'));
        $response->assertSeeInOrder([
            'data-col="actions"',
            'data-apply-filter-list',
            'open-edit-filter-list-modal',
            'open-delete-modal',
        ], false);
    }

    public function test_index_renders_the_apply_label_in_serbian(): void
    {
        FilterList::factory()->create(['user_id' => $this->user->id]);

        $this->get(route('locale.switch', 'sr'));
        $response = $this->get(route('filter-lists.index'));

        $response->assertOk();
        $response->assertSee('Primeni ove filtere na kontrolnoj tabli');
    }

    public function test_index_apply_url_is_the_bare_dashboard_for_an_empty_capture(): void
    {
        $list = FilterList::factory()->create([
            'user_id' => $this->user->id,
            'filters' => [],
        ]);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk();
        $this->assertSame(route('dashboard'), $response->viewData('applyUrls')[$list->id]);
    }

    public function test_store_persists_the_sanitized_capture_behind_a_hash_only_link(): void
    {
        $url = route('filter-lists.store', [
            'filter' => ['search' => 'needle', 'unknown' => 'remove'],
            'sort' => '-username',
        ]);

        $response = $this->post($url, [
            'name' => 'Published list',
            'description' => 'A useful list.',
        ]);

        $response->assertRedirect(route('dashboard', [
            'filter' => ['search' => 'needle', 'unknown' => 'remove'],
            'sort' => '-username',
        ]));
        $response->assertSessionHas('saved_filter_list');
        $list = FilterList::query()->where('name', 'Published list')->firstOrFail();
        $this->assertSame(url('/lists/'.$list->hash), $list->publicUrl());
        $this->assertEquals(['filter' => ['search' => 'needle'], 'sort' => '-username'], $list->filters);
        $this->assertFalse($list->is_published);
        $this->assertNull($list->published_at);
    }

    public function test_store_publishes_when_the_checkbox_is_ticked(): void
    {
        $response = $this->post(
            route('filter-lists.store', ['filter' => ['search' => 'needle']]),
            ['name' => 'Published list', 'is_published' => '1']
        );

        $response->assertRedirect();
        $response->assertSessionHas('saved_filter_list');
        $list = FilterList::query()->where('name', 'Published list')->firstOrFail();
        $this->assertTrue($list->is_published);
        $this->assertNotNull($list->published_at);
    }

    public function test_store_leaves_the_public_url_unreachable_until_published(): void
    {
        $this->post(
            route('filter-lists.store', ['filter' => ['search' => 'needle']]),
            ['name' => 'Private saved list']
        )->assertRedirect();
        $list = FilterList::query()->where('name', 'Private saved list')->firstOrFail();

        $this->post(route('logout'))->assertRedirect();
        $this->get($list->publicUrl())->assertNotFound();
    }

    public function test_store_ignores_a_submitted_slug(): void
    {
        $response = $this->post(route('filter-lists.store', ['filter' => ['search' => 'needle']]), [
            'name' => 'Slug attempt',
            'slug' => 'my-custom-slug',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $list = FilterList::query()->where('name', 'Slug attempt')->firstOrFail();
        $this->assertSame(url('/lists/'.$list->hash), $list->publicUrl());
        $this->assertArrayNotHasKey('slug', $list->getAttributes());
    }

    public function test_store_rejects_an_empty_legal_capture(): void
    {
        $response = $this->post(route('filter-lists.store', ['filter' => ['unknown' => 'value']]), [
            'name' => 'Empty capture',
        ]);

        $response->assertSessionHasErrors('filters');
    }

    public function test_update_can_unpublish_and_republish_with_a_fresh_hash(): void
    {
        $list = FilterList::factory()->create(['user_id' => $this->user->id, 'hash' => 'Original1234']);

        $this->put(route('filter-lists.update', $list), [
            'name' => $list->name,
            'is_published' => '0',
        ])->assertRedirect();
        $this->assertFalse($list->refresh()->is_published);

        $this->put(route('filter-lists.update', $list), [
            'name' => $list->name,
            'is_published' => '1',
        ])->assertRedirect();

        $this->assertTrue($list->refresh()->is_published);
        $this->assertNotSame('Original1234', $list->hash);
    }

    public function test_update_first_publication_keeps_the_create_time_hash(): void
    {
        $list = FilterList::factory()->create([
            'user_id' => $this->user->id,
            'hash' => 'Original1234',
            'is_published' => false,
            'published_at' => null,
        ]);

        $this->put(route('filter-lists.update', $list), [
            'name' => $list->name,
            'is_published' => '1',
        ])->assertRedirect();

        $list->refresh();
        $this->assertSame('Original1234', $list->hash);
        $this->assertTrue($list->is_published);
        $this->assertNotNull($list->published_at);
    }

    public function test_index_edit_modal_labels_the_published_checkbox_without_instructions(): void
    {
        FilterList::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk();
        $response->assertSee(__('messages.filter_list.published_label'));
        $response->assertDontSee('uncheck to unpublish');
    }

    public function test_destroy_soft_deletes_the_owned_list(): void
    {
        $list = FilterList::factory()->create(['user_id' => $this->user->id]);

        $this->delete(route('filter-lists.destroy', $list))->assertRedirect();

        $this->assertSoftDeleted('filter_lists', ['id' => $list->id]);
    }

    public function test_controller_catches_service_failures_for_every_action(): void
    {
        $list = FilterList::factory()->create(['user_id' => $this->user->id]);
        $service = $this->mock(FilterListService::class);
        $service->shouldReceive('getAll')->once()->with(true, true)->andThrow(new Exception('index failed'));
        $service->shouldReceive('sanitizeFilters')->once()->andReturn(['filter' => ['search' => 'needle']]);
        $service->shouldReceive('create')->once()->andThrow(new Exception('store failed'));
        $service->shouldReceive('update')->once()->andThrow(new Exception('update failed'));
        $service->shouldReceive('delete')->once()->andThrow(new Exception('delete failed'));

        $this->get(route('filter-lists.index'))->assertRedirect();
        $this->post(route('filter-lists.store', ['filter' => ['search' => 'needle']]), ['name' => 'Failed'])->assertRedirect();
        $this->put(route('filter-lists.update', $list), ['name' => 'Failed update'])->assertRedirect();
        $this->delete(route('filter-lists.destroy', $list))->assertRedirect();
    }

    public function test_index_can_render_a_mocked_paginator(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 10);
        $service = $this->mock(FilterListService::class);
        $service->shouldReceive('getAll')->once()->with(true, true)->andReturn($paginator);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk()->assertViewHas('filterLists', $paginator);
    }

    public function test_destroy_handles_a_false_service_result(): void
    {
        $list = FilterList::factory()->create(['user_id' => $this->user->id]);
        $service = $this->mock(FilterListService::class);
        $service->shouldReceive('delete')->once()->with($list->id)->andReturn(false);

        $response = $this->delete(route('filter-lists.destroy', $list));

        $response->assertRedirect();
    }

    public function test_non_owner_cannot_mutate_a_filter_list(): void
    {
        $otherOwner = User::withoutEvents(fn (): User => User::factory()->create());
        $otherList = FilterList::factory()->create(['user_id' => $otherOwner->id]);

        $this->put(route('filter-lists.update', $otherList), ['name' => 'No access'])
            ->assertNotFound();
        $this->delete(route('filter-lists.destroy', $otherList))
            ->assertNotFound();
    }
}
