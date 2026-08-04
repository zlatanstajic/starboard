<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class ColumnVisibilityControlComponentTest extends TestCase
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

    public function test_sources_page_binds_a_checkbox_to_every_unlocked_column(): void
    {
        $response = $this->get(route('network-sources.index'));

        $response->assertOk();

        foreach (['number', 'url', 'status', 'profiles', 'timestamps', 'actions'] as $key) {
            $response->assertSee('x-model="columns.'.$key.'"', false);
        }
    }

    public function test_tags_page_binds_a_checkbox_to_every_unlocked_column(): void
    {
        $response = $this->get(route('network-tags.index'));

        $response->assertOk();

        foreach (['number', 'description', 'profiles', 'timestamps', 'actions'] as $key) {
            $response->assertSee('x-model="columns.'.$key.'"', false);
        }
    }

    public function test_name_column_is_locked_visible_on_both_listing_pages(): void
    {
        foreach (['network-sources.index', 'network-tags.index'] as $route) {
            $response = $this->get(route($route));

            $response->assertOk();
            $response->assertDontSee('x-model="columns.name"', false);
            $response->assertSee('<input type="checkbox" checked disabled class="rounded text-blue-600">', false);
            $response->assertSee('cursor-not-allowed opacity-70', false);
        }
    }

    public function test_sources_table_marks_every_column_cell(): void
    {
        NetworkSource::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('network-sources.index'));

        $response->assertOk();

        foreach (['number', 'name', 'url', 'status', 'profiles', 'timestamps', 'actions'] as $key) {
            $response->assertSee('data-col="'.$key.'"', false);
            $response->assertSee('x-show="columns.'.$key.'"', false);
        }
    }

    public function test_tags_table_marks_every_column_cell(): void
    {
        NetworkTag::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('network-tags.index'));

        $response->assertOk();

        foreach (['number', 'name', 'description', 'profiles', 'timestamps', 'actions'] as $key) {
            $response->assertSee('data-col="'.$key.'"', false);
            $response->assertSee('x-show="columns.'.$key.'"', false);
        }
    }

    public function test_dashboard_still_renders_its_full_column_picker(): void
    {
        NetworkSource::factory()->create(['user_id' => $this->user->id]);
        NetworkTag::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();

        foreach (['number', 'tags', 'status', 'favorite', 'visits', 'timestamps', 'actions'] as $key) {
            $response->assertSee('x-model="columns.'.$key.'"', false);
        }

        $response->assertDontSee('x-model="columns.name"', false);
        $response->assertSee(__('messages.default.columns_help'));
    }
}
