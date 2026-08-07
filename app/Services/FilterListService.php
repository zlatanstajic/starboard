<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FilterList;
use App\Models\NetworkProfile;
use App\Repositories\FilterListRepository;
use App\Repositories\NetworkProfileRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class FilterListService
{
    /** Number of public lists highlighted on the landing page. */
    public const int PUBLIC_HIGHLIGHT_LIMIT = 10;

    public function __construct(
        private readonly FilterListRepository $filterListRepository
    ) {
        //
    }

    public function getAll(
        bool $paginate = false,
        bool $filterable = false
    ): LengthAwarePaginator {
        return $this->filterListRepository->getAll($paginate, '-created_at', $filterable);
    }

    public function create(array $data): FilterList
    {
        return $this->filterListRepository->upsert($data);
    }

    public function update(FilterList $filterList, array $data): FilterList
    {
        return $this->filterListRepository->upsert($data, $filterList);
    }

    public function delete(int $id): bool
    {
        return $this->filterListRepository->delete($id);
    }

    public function getPublicList(string $token): ?FilterList
    {
        return $this->filterListRepository->getPublishedByHash($token);
    }

    /**
     * @return Collection<int, FilterList>
     */
    public function getLatestPublic(int $limit = self::PUBLIC_HIGHLIGHT_LIMIT): Collection
    {
        return $this->filterListRepository->getLatestPublished($limit);
    }

    public function unpublish(FilterList $filterList): FilterList
    {
        return $this->filterListRepository->setPublished($filterList, false);
    }

    public function republish(FilterList $filterList): FilterList
    {
        return $this->filterListRepository->setPublished(
            $filterList,
            true,
            $this->filterListRepository->mintHash()
        );
    }

    /**
     * Renders a saved capture as the same labels the dashboard filters show.
     *
     * @param  array<int|string, string>  $sourceNames  Network source id => name.
     * @param  array<int|string, string>  $tagNames  Network tag id => name.
     * @return list<array{label: string, value: string}>
     */
    public function describeFilters(
        FilterList $filterList,
        array $sourceNames = [],
        array $tagNames = []
    ): array {
        $capture = $filterList->filters;
        $filters = is_array($capture['filter'] ?? null) ? $capture['filter'] : [];
        $described = [];

        foreach ($filters as $key => $value) {
            $described[] = [
                'label' => $this->filterLabel((string) $key),
                'value' => $this->filterValue((string) $key, $value, $sourceNames, $tagNames),
            ];
        }

        $sort = $capture['sort'] ?? null;

        if (is_string($sort) && $sort !== '') {
            $described[] = [
                'label' => __('messages.default.sort_by'),
                'value' => $this->sortLabel($sort),
            ];
        }

        return $described;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{filter?: array<string, mixed>, sort?: string}
     */
    public function sanitizeFilters(array $query): array
    {
        return $this->allowedCapture($query);
    }

    /**
     * Expands a saved capture into an explicit dashboard query string, so the
     * dashboard's own filter controls keep working against the applied values.
     */
    public function buildDashboardUrl(FilterList $filterList): string
    {
        $query = $this->allowedCapture($filterList->filters ?? []);

        return $query === []
            ? route('dashboard')
            : route('dashboard', $query);
    }

    /**
     * Shared allow-list gate for both the write path (`sanitizeFilters()`) and
     * the read path (`buildDashboardUrl()`), so the two can never diverge.
     *
     * @param  array<string, mixed>  $query
     * @return array{filter?: array<string, mixed>, sort?: string}
     */
    private function allowedCapture(array $query): array
    {
        $allowedFilters = array_merge(
            NetworkProfile::ALLOWED_FILTERS,
            NetworkProfileRepository::ADDITIONAL_FILTERS
        );
        $filters = is_array($query['filter'] ?? null) ? $query['filter'] : [];
        $filters = array_filter(
            array_intersect_key($filters, array_flip($allowedFilters)),
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );
        $capture = [];

        if ($filters !== []) {
            $capture['filter'] = $filters;
        }

        $sort = $query['sort'] ?? null;
        if (is_string($sort) && in_array(ltrim($sort, '-'), NetworkProfile::ALLOWED_SORTS, true)) {
            $capture['sort'] = $sort;
        }

        return $capture;
    }

    private function filterLabel(string $key): string
    {
        return match ($key) {
            'network_source_id' => __('messages.default.source'),
            'is_public' => __('messages.default.status'),
            'is_favorite' => __('messages.default.favorite'),
            'visits' => __('messages.default.visits'),
            'last_visit' => __('messages.default.last_visit'),
            'new_items' => __('messages.filter_list.new_items_label'),
            'has_description' => __('messages.default.description'),
            'search' => __('messages.default.search'),
            'tags' => __('messages.default.tags'),
            'exclude_tags' => __('messages.default.excluded').' '.__('messages.default.tags'),
            default => $key,
        };
    }

    /**
     * @param  array<int|string, string>  $sourceNames
     * @param  array<int|string, string>  $tagNames
     */
    private function filterValue(
        string $key,
        mixed $value,
        array $sourceNames,
        array $tagNames
    ): string {
        return match ($key) {
            'network_source_id' => $this->namedValues($value, $sourceNames),
            'tags', 'exclude_tags' => $this->tagValue($value, $tagNames),
            'is_public' => $this->booleanLabel(
                $value,
                'messages.network_profile.filter.public_only',
                'messages.network_profile.filter.private_only'
            ),
            'is_favorite' => $this->booleanLabel(
                $value,
                'messages.network_profile.filter.favorites_only',
                'messages.network_profile.filter.non_favorites'
            ),
            'new_items' => $this->booleanLabel(
                $value,
                'messages.network_profile.filter.with_new_items',
                'messages.network_profile.filter.without_new_items'
            ),
            'has_description' => $this->booleanLabel(
                $value,
                'messages.network_profile.filter.with_description',
                'messages.network_profile.filter.without_description'
            ),
            'last_visit' => $this->lastVisitLabel($value),
            default => $this->scalarValue($value),
        };
    }

    private function booleanLabel(mixed $value, string $trueKey, string $falseKey): string
    {
        return filter_var($this->scalarValue($value), FILTER_VALIDATE_BOOLEAN)
            ? __($trueKey)
            : __($falseKey);
    }

    private function lastVisitLabel(mixed $value): string
    {
        return match ($this->scalarValue($value)) {
            '24h' => __('messages.network_profile.filter.time.last_24_hours'),
            '7d' => __('messages.network_profile.filter.time.last_1_7_days'),
            '30d' => __('messages.network_profile.filter.time.last_8_30_days'),
            'older' => __('messages.network_profile.filter.time.over_one_month'),
            'not_24h' => __('messages.network_profile.filter.time.not_last_24_hours'),
            default => $this->scalarValue($value),
        };
    }

    /**
     * The tag filter doubles as an any/none switch, so those two
     * sentinels need their own labels before falling back to tag names.
     *
     * @param  array<int|string, string>  $tagNames
     */
    private function tagValue(mixed $value, array $tagNames): string
    {
        return match ($this->scalarValue($value)) {
            'any' => __('messages.network_profile.filter.with_tags'),
            'none' => __('messages.network_profile.filter.without_tags'),
            default => $this->namedValues($value, $tagNames),
        };
    }

    /**
     * @param  array<int|string, string>  $names
     */
    private function namedValues(mixed $value, array $names): string
    {
        $ids = is_array($value) ? $value : [$value];
        $labels = [];

        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }

            $labels[] = $names[(string) $id] ?? (string) $id;
        }

        return $labels === [] ? '' : implode(', ', $labels);
    }

    private function scalarValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function sortLabel(string $sort): string
    {
        $labels = __('messages.network_profile.sort');

        return is_array($labels) && isset($labels[$sort]) ? (string) $labels[$sort] : $sort;
    }
}
