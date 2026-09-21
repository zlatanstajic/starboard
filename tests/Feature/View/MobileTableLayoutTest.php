<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\FilterList;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\TestCase;

/**
 * The listing tables scroll horizontally inside their `overflow-x-auto` wrapper.
 * A free-text cell without `whitespace-nowrap` is the one column an auto-layout
 * table can shrink, so on a phone it collapses to a few characters per line and
 * the row grows several times taller instead of the table scrolling. The
 * dashboard avoids this by keeping its text cells on one line; these assertions
 * hold the other tables to the same rule.
 */
class MobileTableLayoutTest extends TestCase
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

    public function test_filter_lists_table_keeps_its_text_cells_on_one_line(): void
    {
        FilterList::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Creators with many visits',
            'description' => 'A saved dashboard capture kept on a single line.',
        ]);

        $response = $this->get(route('filter-lists.index'));

        $response->assertOk();

        foreach (['name', 'description', 'filters'] as $column) {
            $response->assertSee('whitespace-nowrap px-6 py-4', false);
            $this->assertMatchesRegularExpression(
                '/<td class="whitespace-nowrap[^"]*"[^>]*data-col="'.$column.'"/',
                (string) $response->getContent(),
                "The filter-lists {$column} cell must not wrap on a phone.",
            );
        }
    }

    public function test_public_list_table_keeps_its_text_cells_on_one_line(): void
    {
        $source = NetworkSource::factory()->create(['user_id' => $this->user->id]);
        $tag = NetworkTag::factory()->create(['user_id' => $this->user->id]);

        $profile = NetworkProfile::factory()->create([
            'user_id' => $this->user->id,
            'network_source_id' => $source->id,
            'is_public' => true,
            'description' => 'A long form creator description kept on a single line.',
        ]);
        $profile->networkTags()->attach($tag->id);

        $filterList = FilterList::factory()->create([
            'user_id' => $this->user->id,
            'filters' => ['filter' => ['is_public' => '1']],
        ]);

        $response = $this->get(route('filter-lists.show', $filterList->hash));

        $response->assertOk();

        // Structural, so the assertion holds for any number of rendered rows:
        // a data cell that kept the bare padding class is one that can wrap.
        $this->assertSame(
            0,
            substr_count((string) $response->getContent(), '<td class="px-6 py-4"'),
            'Every public list cell (name, source, tags, description) must stay on one line.',
        );
    }

    public function test_landing_page_public_list_description_stays_on_one_line(): void
    {
        FilterList::factory()->create([
            'user_id' => $this->user->id,
            'description' => 'A published capture whose description must not wrap.',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertStringContainsString(
            '<td class="whitespace-nowrap px-6 py-4" title=',
            (string) $response->getContent(),
            'The landing page description cell must not wrap on a phone.',
        );
    }

    public function test_long_free_text_is_truncated_so_a_one_line_cell_stays_readable(): void
    {
        $source = NetworkSource::factory()->create(['user_id' => $this->user->id]);

        NetworkProfile::factory()->create([
            'user_id' => $this->user->id,
            'network_source_id' => $source->id,
            'is_public' => true,
            'description' => str_repeat('very long description ', 20),
        ]);

        $filterList = FilterList::factory()->create([
            'user_id' => $this->user->id,
            'filters' => ['filter' => ['is_public' => '1']],
        ]);

        $response = $this->get(route('filter-lists.show', $filterList->hash));

        $response->assertOk();

        // A one-line cell only stays readable while its text is capped, so no
        // rendered cell may carry an unbounded value. Checking every cell keeps
        // this independent of which rows the listing happens to paginate onto.
        preg_match_all(
            '#<td class="whitespace-nowrap[^"]*"[^>]*>(.*?)</td>#s',
            (string) $response->getContent(),
            $matches,
        );

        $this->assertNotEmpty($matches[1], 'The public list rendered no one-line cells to check.');

        foreach ($matches[1] as $cell) {
            $text = trim(html_entity_decode(strip_tags($cell)));

            $this->assertLessThanOrEqual(
                50,
                mb_strlen($text),
                'A one-line cell rendered an uncapped value: '.mb_substr($text, 0, 60),
            );
        }
    }
}
