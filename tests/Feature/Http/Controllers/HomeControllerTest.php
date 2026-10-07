<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\FilterList;
use App\Models\User;
use App\Services\FilterListService;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class HomeControllerTest extends TestCase
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

    public function test_landing_page_embeds_the_app_preview_in_both_languages(): void
    {
        foreach (['en' => 'See Starboard in Action', 'sr' => 'Pogledajte Starboard u akciji'] as $locale => $title) {
            $response = $this->withSession(['locale' => $locale])->get(route('home'));

            $response->assertOk();
            $response->assertSee('src="https://www.youtube.com/embed/gWTeE74NwX4"', false);
            $response->assertSee('title="'.$title.'"', false);
            $response->assertSee('aspect-video min-h-[200px] w-full', false);
            $response->assertSee('loading="lazy"', false);
            $response->assertSee('allowfullscreen', false);
            $response->assertDontSee('autoplay=1', false);
            $response->assertDontSee('app-preview-title', false);
            $response->assertSeeInOrder([
                'https://www.youtube.com/embed/gWTeE74NwX4',
                'https://www.producthunt.com/products/starboard',
                'id="public-lists"',
            ], false);
        }
    }

    public function test_app_preview_is_absent_from_login_and_dashboard_pages(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('youtube.com/embed/gWTeE74NwX4', false);

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('youtube.com/embed/gWTeE74NwX4', false);
    }

    public function test_landing_page_lists_public_filter_lists_with_their_public_links(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $published = FilterList::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Published capture',
            'description' => 'Visible to everyone',
        ]);
        $unpublished = FilterList::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Hidden capture',
            'is_published' => false,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertViewIs('welcome');
        $response->assertSee(__('messages.welcome.public_lists_description'));
        $response->assertSee('Published capture');
        $response->assertSee($published->publicUrl());
        $response->assertDontSee('Hidden capture');
        $response->assertDontSee($unpublished->publicUrl());
    }

    public function test_public_list_links_open_in_the_same_tab_and_a_missing_description_renders_a_dash(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        $list = FilterList::factory()->create([
            'user_id' => $owner->id,
            'name' => 'No description capture',
            'description' => null,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(
            '<a href="'.$list->publicUrl().'" title="'.$list->publicUrl().'" class=',
            false
        );
        $response->assertSee('>-</td>', false);
    }

    public function test_landing_page_shows_at_most_ten_public_lists(): void
    {
        $owner = User::withoutEvents(fn (): User => User::factory()->create());
        FilterList::factory()
            ->count(FilterListService::PUBLIC_HIGHLIGHT_LIMIT + 2)
            ->create(['user_id' => $owner->id, 'description' => null]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $lists = $response->viewData('publicFilterLists');
        $this->assertCount(FilterListService::PUBLIC_HIGHLIGHT_LIMIT, $lists);
    }

    public function test_landing_page_shows_an_empty_state_when_nothing_is_published(): void
    {
        FilterList::query()->withoutGlobalScopes()->forceDelete();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(__('messages.welcome.public_lists_empty'));
    }
}
