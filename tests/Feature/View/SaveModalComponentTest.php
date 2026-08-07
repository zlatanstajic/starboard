<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class SaveModalComponentTest extends TestCase
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

    public function test_save_button_is_hidden_without_filter_or_sort(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('data-save-filter-list', false);
    }

    public function test_save_button_and_modal_render_for_filter_and_sort_captures(): void
    {
        foreach ([
            ['filter' => ['search' => 'needle']],
            ['sort' => '-username'],
        ] as $query) {
            $response = $this->get(route('dashboard', $query));

            $response->assertOk();
            $response->assertSee('data-save-filter-list', false);
            $response->assertSee('name="name"', false);
            $response->assertDontSee('name="slug"', false);
            $response->assertSee('name="description"', false);
            $response->assertSee(route('filter-lists.store'), false);
        }
    }

    public function test_save_button_renders_directly_after_the_clear_button(): void
    {
        $response = $this->get(route('dashboard', ['filter' => ['search' => 'needle']]));

        $response->assertOk();
        $response->assertSeeInOrder(['data-clear-filters', 'data-save-filter-list'], false);
    }

    public function test_save_form_action_carries_the_current_query_string(): void
    {
        $response = $this->get(route('dashboard', [
            'filter' => ['search' => 'needle'],
            'sort' => '-username',
        ]));

        $response->assertOk();
        $response->assertSee('filter%5Bsearch%5D=needle', false);
        $response->assertSee('sort=-username', false);
    }

    public function test_save_modal_published_checkbox_defaults_to_unchecked(): void
    {
        $response = $this->get(route('dashboard', ['filter' => ['search' => 'needle']]));

        $response->assertOk();
        $response->assertSee('name="is_published" value="0"', false);
        $response->assertSee(
            '<input id="save-list-published" name="is_published" type="checkbox" value="1" class="h-4 w-4 rounded border-gray-300 text-indigo-600">',
            false
        );
        $response->assertSee(__('messages.filter_list.published_label'));
    }

    public function test_save_modal_heading_and_submit_use_the_save_labels(): void
    {
        $response = $this->get(route('dashboard', ['filter' => ['search' => 'needle']]));

        $response->assertOk();
        $response->assertSee(__('messages.filter_list.save'));
        $response->assertSee(__('messages.default.save'));

        $this->get(route('locale.switch', 'sr'));

        $this->get(route('dashboard', ['filter' => ['search' => 'needle']]))
            ->assertOk()
            ->assertSee('Sačuvaj listu filtera');
    }

    public function test_result_modal_auto_opens_and_exposes_copy_failure_state(): void
    {
        $url = url('/lists/Hash12345678');

        $response = $this->withSession(['saved_filter_list' => [
            'name' => 'Saved list',
            'url' => $url,
        ]])->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('x-init="isOpen = true"', false);
        $response->assertSee($url, false);
        $response->assertSee('navigator.clipboard', false);
        $response->assertSee(__('messages.filter_list.copy_failed'));
    }

    public function test_modal_cancel_buttons_share_the_edit_modal_hover_styles(): void
    {
        $response = $this->withSession(['saved_filter_list' => [
            'name' => 'Saved list',
            'url' => url('/lists/Hash12345678'),
        ]])->get(route('dashboard', ['filter' => ['search' => 'needle']]));

        $response->assertOk();
        $this->assertSame(
            2,
            substr_count(
                $response->getContent(),
                '@click="isOpen = false" class="inline-flex justify-center px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 dark:text-white rounded-lg hover:bg-gray-200 transition-colors hover:text-black"'
            )
        );
    }
}
