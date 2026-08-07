<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\FilterList;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

class FilterToggleButtonComponentTest extends TestCase
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

    /**
     * The public list page and the authenticated listings must render the very
     * same button, so the only permitted difference is the layout-only class
     * the public header appends through the attribute bag.
     */
    public function test_the_public_and_authenticated_toggles_share_one_appearance(): void
    {
        $list = FilterList::factory()->create([
            'user_id' => User::withoutEvents(fn (): User => User::factory()->create())->id,
        ]);

        $public = $this->get(route('filter-lists.show', $list->hash));

        $this->actingAs(User::factory()->create());
        $authenticated = $this->get(route('network-sources.index'));

        $public->assertOk();
        $authenticated->assertOk();

        $authenticatedClasses = $this->toggleButtonClasses($authenticated->getContent());

        $this->assertSame(
            $authenticatedClasses.' shrink-0',
            $this->toggleButtonClasses($public->getContent())
        );
    }

    public function test_both_toggles_carry_the_shared_label_and_expanded_state(): void
    {
        $list = FilterList::factory()->create([
            'user_id' => User::withoutEvents(fn (): User => User::factory()->create())->id,
        ]);

        $public = $this->get(route('filter-lists.show', $list->hash));

        $this->actingAs(User::factory()->create());
        $authenticated = $this->get(route('network-sources.index'));

        foreach ([$public, $authenticated] as $response) {
            $response->assertOk();
            $response->assertSee('data-filter-toggle', false);
            $response->assertSee(':aria-expanded="showFilters"', false);
            $response->assertSee(__('messages.default.toggle_filters'));
        }
    }

    private function toggleButtonClasses(string $html): string
    {
        $this->assertSame(
            1,
            preg_match('/<button data-filter-toggle\b[^>]*>/', $html, $button),
            'The response does not render a filter toggle button.'
        );
        $this->assertSame(
            1,
            preg_match('/class="([^"]*)"/', $button[0], $classes),
            'The filter toggle button does not carry a class attribute.'
        );

        return $classes[1];
    }
}
