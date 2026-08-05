<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class PublishModalComponentTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $user = User::withoutEvents(fn (): User => User::factory()->create());
        $this->actingAs($user);
    }

    #[Override]
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_publish_button_is_hidden_without_filter_or_sort(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('data-publish-filter-list', false);
    }

    public function test_publish_button_and_modal_render_for_filter_and_sort_captures(): void
    {
        foreach ([
            ['filter' => ['search' => 'needle']],
            ['sort' => '-username'],
        ] as $query) {
            $response = $this->get(route('dashboard', $query));

            $response->assertOk();
            $response->assertSee('data-publish-filter-list', false);
            $response->assertSee('name="name"', false);
            $response->assertDontSee('name="slug"', false);
            $response->assertSee('name="description"', false);
            $response->assertSee(route('filter-lists.store'), false);
        }
    }

    public function test_publish_button_renders_directly_after_the_clear_button(): void
    {
        $response = $this->get(route('dashboard', ['filter' => ['search' => 'needle']]));

        $response->assertOk();
        $response->assertSeeInOrder(['data-clear-filters', 'data-publish-filter-list'], false);
    }

    public function test_publish_form_action_carries_the_current_query_string(): void
    {
        $response = $this->get(route('dashboard', [
            'filter' => ['search' => 'needle'],
            'sort' => '-username',
        ]));

        $response->assertOk();
        $response->assertSee('filter%5Bsearch%5D=needle', false);
        $response->assertSee('sort=-username', false);
    }

    public function test_result_modal_auto_opens_and_exposes_copy_failure_state(): void
    {
        $url = url('/lists/Hash12345678');

        $response = $this->withSession(['published_filter_list' => [
            'name' => 'Published list',
            'url' => $url,
        ]])->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('x-init="isOpen = true"', false);
        $response->assertSee($url, false);
        $response->assertSee('navigator.clipboard', false);
        $response->assertSee(__('messages.filter_list.copy_failed'));
    }
}
