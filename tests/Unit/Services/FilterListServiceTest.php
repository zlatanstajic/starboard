<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\FilterList;
use App\Repositories\FilterListRepository;
use App\Services\FilterListService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery\MockInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilterListServiceTest extends TestCase
{
    private FilterListRepository|MockInterface $repository;

    private FilterListService $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->mock(FilterListRepository::class);
        $this->service = new FilterListService($this->repository);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function lastVisitRangeProvider(): array
    {
        return [
            '24h' => ['24h', 'messages.network_profile.filter.time.last_24_hours'],
            '7d' => ['7d', 'messages.network_profile.filter.time.last_1_7_days'],
            '30d' => ['30d', 'messages.network_profile.filter.time.last_8_30_days'],
            'older' => ['older', 'messages.network_profile.filter.time.over_one_month'],
            'not_24h' => ['not_24h', 'messages.network_profile.filter.time.not_last_24_hours'],
            'unmapped range falls back to the raw value' => ['1y', '1y'],
        ];
    }

    public function test_sanitize_filters_keeps_only_supported_non_empty_values_and_legal_sort(): void
    {
        $capture = $this->service->sanitizeFilters([
            'filter' => [
                'network_source_id' => '4',
                'search' => 'needle',
                'is_public' => '0',
                'unknown' => 'remove',
                'tags' => [],
            ],
            'sort' => '-username',
        ]);

        $this->assertSame([
            'filter' => [
                'network_source_id' => '4',
                'search' => 'needle',
                'is_public' => '0',
            ],
            'sort' => '-username',
        ], $capture);
        $this->assertSame([], $this->service->sanitizeFilters(['sort' => 'illegal']));
    }

    public function test_crud_and_listing_delegate_to_repository(): void
    {
        $list = new FilterList(['name' => 'List']);
        $paginator = new LengthAwarePaginator([$list], 1, 10);
        $this->repository->shouldReceive('getAll')->once()->with(true, '-created_at', true)->andReturn($paginator);
        $this->repository->shouldReceive('upsert')->once()->with(['name' => 'List'])->andReturn($list);
        $this->repository->shouldReceive('upsert')->once()->with(['name' => 'Updated'], $list)->andReturn($list);
        $this->repository->shouldReceive('delete')->once()->with(1)->andReturn(true);

        $this->assertSame($paginator, $this->service->getAll(paginate: true, filterable: true));
        $this->assertSame($list, $this->service->create(['name' => 'List']));
        $this->assertSame($list, $this->service->update($list, ['name' => 'Updated']));
        $this->assertTrue($this->service->delete(1));
    }

    public function test_public_lookup_passes_the_token_through_as_the_hash(): void
    {
        $list = new FilterList(['hash' => 'Hash12345678']);
        $this->repository->shouldReceive('getPublishedByHash')->once()->with('Hash12345678')->andReturn($list);

        $result = $this->service->getPublicList('Hash12345678');

        $this->assertSame($list, $result);
    }

    public function test_get_latest_public_delegates_with_the_highlight_limit(): void
    {
        $lists = new Collection([new FilterList(['name' => 'Public list'])]);
        $this->repository
            ->shouldReceive('getLatestPublished')
            ->once()
            ->with(FilterListService::PUBLIC_HIGHLIGHT_LIMIT)
            ->andReturn($lists);

        $this->assertSame($lists, $this->service->getLatestPublic());
    }

    public function test_unpublish_and_republish_delegate_with_a_fresh_hash(): void
    {
        $list = new FilterList(['hash' => 'OldHash12345']);
        $this->repository->shouldReceive('setPublished')->once()->with($list, false)->andReturn($list);
        $this->repository->shouldReceive('mintHash')->once()->andReturn('FreshHash123');
        $this->repository->shouldReceive('setPublished')->once()->with($list, true, 'FreshHash123')->andReturn($list);

        $this->assertSame($list, $this->service->unpublish($list));
        $this->assertSame($list, $this->service->republish($list));
    }

    public function test_publish_reuses_the_hash_for_a_never_published_list(): void
    {
        $list = new FilterList([
            'hash' => 'Original1234',
            'published_at' => null,
        ]);
        $this->repository->shouldReceive('mintHash')->never();
        $this->repository->shouldReceive('setPublished')->once()->with($list, true)->andReturn($list);

        $this->assertSame($list, $this->service->publish($list));
    }

    public function test_publish_mints_a_fresh_hash_for_a_previously_published_list(): void
    {
        $list = new FilterList([
            'hash' => 'Original1234',
            'published_at' => now()->subDay(),
        ]);
        $this->repository->shouldReceive('mintHash')->once()->andReturn('FreshHash123');
        $this->repository
            ->shouldReceive('setPublished')
            ->once()
            ->with($list, true, 'FreshHash123')
            ->andReturn($list);

        $this->assertSame($list, $this->service->publish($list));
    }

    public function test_describe_filters_labels_every_supported_key_and_the_sort(): void
    {
        $list = new FilterList(['filters' => [
            'filter' => [
                'network_source_id' => '1',
                'is_public' => '1',
                'is_favorite' => '0',
                'visits' => '11-20',
                'last_visit' => '7d',
                'new_items' => '1',
                'has_description' => '0',
                'search' => 'needle',
                'tags' => ['2', '3'],
                'exclude_tags' => '4',
            ],
            'sort' => '-number_of_visits',
        ]]);

        $described = $this->service->describeFilters(
            $list,
            ['1' => 'YouTube'],
            ['2' => 'Sports', '3' => 'News', '4' => 'Old']
        );

        $this->assertSame([
            ['label' => __('messages.default.source'), 'value' => 'YouTube'],
            ['label' => __('messages.default.status'), 'value' => __('messages.network_profile.filter.public_only')],
            ['label' => __('messages.default.favorite'), 'value' => __('messages.network_profile.filter.non_favorites')],
            ['label' => __('messages.default.visits'), 'value' => '11-20'],
            ['label' => __('messages.default.last_visit'), 'value' => __('messages.network_profile.filter.time.last_1_7_days')],
            ['label' => __('messages.filter_list.new_items_label'), 'value' => __('messages.network_profile.filter.with_new_items')],
            ['label' => __('messages.default.description'), 'value' => __('messages.network_profile.filter.without_description')],
            ['label' => __('messages.default.search'), 'value' => 'needle'],
            ['label' => __('messages.default.tags'), 'value' => 'Sports, News'],
            ['label' => __('messages.default.excluded').' '.__('messages.default.tags'), 'value' => 'Old'],
            ['label' => __('messages.default.sort_by'), 'value' => __('messages.network_profile.sort.-number_of_visits')],
        ], $described);
    }

    public function test_describe_filters_labels_the_any_and_none_tag_sentinels(): void
    {
        $any = new FilterList(['filters' => ['filter' => ['tags' => 'any']]]);
        $none = new FilterList(['filters' => ['filter' => ['exclude_tags' => 'none']]]);

        $this->assertSame(
            __('messages.network_profile.filter.with_tags'),
            $this->service->describeFilters($any)[0]['value']
        );
        $this->assertSame(
            __('messages.network_profile.filter.without_tags'),
            $this->service->describeFilters($none)[0]['value']
        );
    }

    public function test_describe_filters_falls_back_to_raw_values_for_unknown_keys_and_ids(): void
    {
        $list = new FilterList(['filters' => [
            'filter' => ['network_source_id' => '99', 'mystery' => 'raw'],
            'sort' => 'unmapped',
        ]]);

        $this->assertSame([
            ['label' => __('messages.default.source'), 'value' => '99'],
            ['label' => 'mystery', 'value' => 'raw'],
            ['label' => __('messages.default.sort_by'), 'value' => 'unmapped'],
        ], $this->service->describeFilters($list));
    }

    public function test_describe_filters_returns_nothing_for_an_empty_or_malformed_capture(): void
    {
        $this->assertSame([], $this->service->describeFilters(new FilterList(['filters' => []])));
        $this->assertSame([], $this->service->describeFilters(
            new FilterList(['filters' => ['filter' => 'not-an-array', 'sort' => '']])
        ));
    }

    #[DataProvider('lastVisitRangeProvider')]
    public function test_describe_filters_labels_every_last_visit_range(
        string $stored,
        string $expectedKey
    ): void {
        $list = new FilterList(['filters' => ['filter' => ['last_visit' => $stored]]]);

        $described = $this->service->describeFilters($list);

        $this->assertSame(__($expectedKey), $described[0]['value']);
    }

    public function test_describe_filters_skips_blank_ids_and_joins_nested_values(): void
    {
        $list = new FilterList(['filters' => ['filter' => [
            'tags' => ['', null, '7'],
            'visits' => ['1-5', '6-10'],
        ]]]);

        $described = $this->service->describeFilters($list, [], ['7' => 'Kept']);

        $this->assertSame('Kept', $described[0]['value']);
        $this->assertSame('1-5, 6-10', $described[1]['value']);
    }

    public function test_build_dashboard_url_expands_a_scalar_capture_into_query_parameters(): void
    {
        $list = new FilterList(['filters' => [
            'filter' => ['network_source_id' => '4', 'search' => 'needle'],
            'sort' => '-username',
        ]]);

        $url = $this->service->buildDashboardUrl($list);

        $this->assertStringStartsWith(route('dashboard').'?', $url);
        $this->assertSame([
            'filter' => ['network_source_id' => '4', 'search' => 'needle'],
            'sort' => '-username',
        ], $this->queryOf($url));
    }

    public function test_build_dashboard_url_keeps_array_values_as_repeated_parameters(): void
    {
        $list = new FilterList(['filters' => [
            'filter' => ['tags' => ['3', '7'], 'exclude_tags' => ['9']],
        ]]);

        $query = $this->queryOf($this->service->buildDashboardUrl($list));

        $this->assertSame(['tags' => ['3', '7'], 'exclude_tags' => ['9']], $query['filter']);
    }

    public function test_build_dashboard_url_drops_unknown_filter_keys_and_stale_sorts(): void
    {
        $list = new FilterList(['filters' => [
            'filter' => ['search' => 'needle', 'renamed_key' => 'gone'],
            'sort' => '-removed_column',
        ]]);

        $query = $this->queryOf($this->service->buildDashboardUrl($list));

        $this->assertSame(['filter' => ['search' => 'needle']], $query);
    }

    public function test_build_dashboard_url_returns_the_bare_dashboard_for_an_empty_capture(): void
    {
        $this->assertSame(
            route('dashboard'),
            $this->service->buildDashboardUrl(new FilterList(['filters' => []]))
        );
        $this->assertSame(
            route('dashboard'),
            $this->service->buildDashboardUrl(new FilterList)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }
}
