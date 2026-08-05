<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NetworkProfile\NetworkProfileDuplicationException;
use App\Models\FilterList;
use App\Models\NetworkProfile;
use App\Models\NetworkSource;
use App\Models\NetworkTag;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;

class NetworkProfileRepository extends Repository
{
    public const array ADDITIONAL_FILTERS = [
        'visits',
        'last_visit',
        'new_items',
        'has_description',
        'search',
        'tags',
        'exclude_tags',
    ];

    /**
     * Exposed tag ids per FilterList id, memoized for the request: the public
     * tag filter callback would otherwise replay the saved capture once per
     * filtered request on top of the two passes the listing already runs.
     *
     * @var array<int, list<string>>
     */
    private array $exposedTagIds = [];

    /**
     * Gets all network profile
     */
    public function getAll(
        array $includes = [
            'networkSource',
            'user',
            'networkTags',
        ],
        string $defaultSort = 'last_visit_at',
        ?Request $request = null
    ): LengthAwarePaginator {
        $request ??= request();
        $query = $this->buildStandardQuery(
            NetworkProfile::class,
            filters: $this->additionalAllowedFilters(),
            request: $request
        )
            ->defaultSort($defaultSort);

        // If no specific network source was selected (i.e. "All Network Sources"),
        // exclude profiles that belong to network sources marked as excluded.
        if (! $request->input('filter.network_source_id')) {
            $query->whereHas('networkSource', function (Builder $query): void {
                // Ignore global scopes (like UserScope) on the NetworkSource
                // subquery so tests that create sources with different owners
                // still match when evaluating the exclude flag.
                $query->withoutGlobalScopes()->where('exclude_from_dashboard', false);
            });
        }

        return $query->with($includes)
            ->paginate($this->itemsPerPage)
            ->withQueryString();
    }

    /**
     * Replays the owner's saved filters before applying the visitor's reduced
     * filter set. Both passes intentionally mutate the same Eloquent builder.
     */
    public function getForFilterList(
        FilterList $filterList,
        ?Request $visitorRequest = null
    ): LengthAwarePaginator {
        $savedSort = $filterList->filters['sort'] ?? null;
        $savedSort = is_string($savedSort) ? $savedSort : 'last_visit_at';
        $passOne = $this->buildSavedFilterListQuery($filterList);

        $publicSorts = $this->publicAllowedSorts();
        $savedSortName = ltrim($savedSort, '-');
        $descending = str_starts_with($savedSort, '-');
        $publicSort = collect($publicSorts)
            ->first(fn (AllowedSort $sort): bool => $sort->isSort($savedSortName));

        $publicSort ??= AllowedSort::field($savedSortName);

        $passTwo = $this->buildStandardQuery(
            NetworkProfile::class,
            filters: $this->publicAllowedFilters($filterList),
            sorts: $publicSorts,
            subject: $passOne->getEloquentBuilder(),
            request: $visitorRequest ?? request(),
            includeModelFilters: false,
            includeModelIncludes: false,
            includeModelSorts: false
        )->defaultSort(
            (clone $publicSort)->defaultDirection(
                $descending ? SortDirection::DESCENDING : SortDirection::ASCENDING
            )
        );

        return $passTwo
            ->with([
                'networkSource' => function (Relation $relation) use ($filterList): void {
                    $this->constrainToOwner($relation, NetworkSource::class, $filterList->user_id);
                },
                'networkTags' => function (Relation $relation) use ($filterList): void {
                    $this->constrainToOwner($relation, NetworkTag::class, $filterList->user_id);
                },
            ])
            ->paginate($this->itemsPerPage)
            ->withQueryString();
    }

    /**
     * Network sources actually represented in a published list's profile set.
     *
     * @return Collection<int, NetworkSource>
     */
    public function getSourcesForFilterList(FilterList $filterList): Collection
    {
        return $this->ownerScopedQuery(NetworkSource::query(), $filterList->user_id)
            ->whereIn('id', $this->buildSavedFilterListQuery($filterList)
                ->getEloquentBuilder()
                ->select('network_profiles.network_source_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Network tags actually attached to a published list's profile set.
     *
     * @return Collection<int, NetworkTag>
     */
    public function getTagsForFilterList(FilterList $filterList): Collection
    {
        $profiles = $this->buildSavedFilterListQuery($filterList)
            ->getEloquentBuilder()
            ->select('network_profiles.id');

        return $this->ownerScopedQuery(NetworkTag::query(), $filterList->user_id)
            ->whereHas('networkProfiles', function (Builder $query) use ($profiles): void {
                $query->withoutGlobalScopes()
                    ->whereIn('network_profiles.id', $profiles);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Updates network profile if provided, otherwise
     * inserts new profile with given data.
     *
     * @throws Exception
     * @throws NetworkProfileDuplicationException
     */
    public function upsert(
        array $data,
        ?NetworkProfile $networkProfile = null
    ): NetworkProfile {
        try {
            return $networkProfile
                ? $this->update($networkProfile, $data)
                : $this->create($data);
        } catch (Exception $e) {
            if ($e->getCode() === '23000') {
                throw new NetworkProfileDuplicationException(
                    $data['username'] ?? $networkProfile->username ?? ''
                );
            }

            throw $e;
        }
    }

    /**
     * Deletes network profile for given id.
     */
    public function delete(int $id): bool
    {
        return (bool) NetworkProfile::destroy($id);
    }

    /**
     * Gets network profiles whose network source is a YouTube videos page,
     * streamed lazily via a cursor. When $onlyMatchingFilters is true, the
     * same filters the dashboard listing (getAll) supports are applied from
     * the current request, so only profiles matching them are returned.
     *
     * @return LazyCollection<int, NetworkProfile>
     */
    public function getYouTubeVideoProfiles(bool $onlyMatchingFilters = false): LazyCollection
    {
        $query = $onlyMatchingFilters
            ? $this->buildStandardQuery(NetworkProfile::class, filters: $this->additionalAllowedFilters())
            : NetworkProfile::query();

        return $query
            ->whereHas('networkSource', function (Builder $query): void {
                $query->withoutGlobalScopes()
                    ->where(function (Builder $q): void {
                        $q->where('url', 'like', 'https://youtube.com/%/videos%')
                            ->orWhere('url', 'like', 'https://www.youtube.com/%/videos%');
                    });
            })
            ->with('networkSource')
            ->cursor();
    }

    /**
     * Increments network profile's number_of_visits
     * and sets last_visit_at to now.
     */
    public function increment(NetworkProfile $networkProfile): NetworkProfile
    {
        $networkProfile->increment('number_of_visits', 1, [
            'last_visit_at' => now(),
            'new_items' => 0,
        ]);

        return $networkProfile;
    }

    /**
     * The scope/callback filters layered on top of NetworkProfile's own
     * ALLOWED_FILTERS (which buildStandardQuery merges in automatically).
     *
     * @return list<AllowedFilter>
     */
    private function additionalAllowedFilters(): array
    {
        return [
            AllowedFilter::scope('visits', 'byVisits'),
            AllowedFilter::scope('last_visit', 'byLastVisit'),
            AllowedFilter::scope('new_items', 'byNewItems'),
            AllowedFilter::callback('has_description', $this->filterHasDescription(...)),
            AllowedFilter::callback('search', $this->filterSearch(...)),
            AllowedFilter::callback('tags', $this->filterTags(...)),
            AllowedFilter::callback('exclude_tags', $this->filterExcludeTags(...)),
        ];
    }

    /**
     * The owner's saved capture as a query: public profiles they own, narrowed
     * by the saved filters and (unless the capture pins a source) restricted to
     * sources not excluded from the dashboard. This is the single definition of
     * "what the list exposes", shared by the public listing and the public
     * filter dropdowns so the two can never disagree.
     */
    private function buildSavedFilterListQuery(FilterList $filterList): QueryBuilder
    {
        $savedFilters = $filterList->filters['filter'] ?? [];
        $savedRequest = Request::create('/', 'GET', ['filter' => $savedFilters]);

        $owned = $this->ownerScopedQuery(NetworkProfile::query(), $filterList->user_id)
            ->where('network_profiles.is_public', true);

        $query = $this->buildStandardQuery(
            NetworkProfile::class,
            filters: $this->additionalAllowedFilters(),
            subject: $owned,
            request: $savedRequest
        );

        if (! $savedRequest->input('filter.network_source_id')) {
            $query->whereHas('networkSource', function (Builder $query) use ($filterList): void {
                $this->constrainToOwner($query, NetworkSource::class, $filterList->user_id);
                $query->where('exclude_from_dashboard', false);
            });
        }

        return $query;
    }

    /**
     * The only sorts a public list visitor may address: name and username.
     *
     * @return list<AllowedSort>
     */
    private function publicAllowedSorts(): array
    {
        return [
            AllowedSort::callback('name', function (Builder $query, bool $descending): void {
                $query->orderByRaw(
                    'lower(coalesce(network_profiles.title, network_profiles.username)) '
                        .($descending ? 'desc' : 'asc')
                );
            }),
            AllowedSort::field('username', 'network_profiles.username'),
        ];
    }

    /** @return list<AllowedFilter> */
    private function publicAllowedFilters(FilterList $filterList): array
    {
        return [
            AllowedFilter::exact('network_source_id'),
            AllowedFilter::callback('search', $this->filterSearch(...)),
            AllowedFilter::callback(
                'tags',
                function (Builder $query, string|array $value) use ($filterList): void {
                    $this->filterPublicTags($query, $value, $filterList);
                }
            ),
        ];
    }

    /**
     * The visitor-facing tags filter. filterTags() bypasses UserScope on the
     * pivot lookup, so an id the list does not expose would answer "does
     * profile X carry tag Y" for a stranger's tag. Incoming ids are therefore
     * intersected with the list's own exposed tag set; if nothing survives, the
     * filter stays a filter and matches no profile rather than being ignored.
     *
     * The 'any'/'none' sentinels the public dropdown also offers expose no tag
     * identity at all, so they short-circuit straight to filterTags() — running
     * them through the id intersection would leave them as literal strings that
     * match no exposed id and silently return zero profiles.
     */
    private function filterPublicTags(
        Builder $query,
        string|array $value,
        FilterList $filterList
    ): void {
        if (empty($value)) {
            return;
        }

        if ($value === 'any' || $value === 'none') {
            $this->filterTags($query, $value);

            return;
        }

        $requested = $this->normalizeTagIds($value);

        if (empty($requested)) {
            return;
        }

        $allowed = array_values(array_intersect($requested, $this->exposedTagIds($filterList)));

        if (empty($allowed)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $this->filterTags($query, $allowed);
    }

    /**
     * The list's exposed tag ids as strings, resolved once per FilterList.
     *
     * @return list<string>
     */
    private function exposedTagIds(FilterList $filterList): array
    {
        return $this->exposedTagIds[$filterList->id] ??= $this->getTagsForFilterList($filterList)
            ->map(fn (NetworkTag $tag): string => (string) $tag->id)
            ->values()
            ->all();
    }

    /**
     * Gets network profile by username from trashed ones.
     */
    private function getByUsername(
        string $username,
        ?int $networkSourceId = null
    ): ?NetworkProfile {
        $query = NetworkProfile::onlyTrashed()
            ->where('username', $username);

        if ($networkSourceId) {
            $query->where('network_source_id', $networkSourceId);
        }

        return $query->first();
    }

    /**
     * Creates new network profile or restores
     * softly deleted one if exists.
     */
    private function create(array $data): NetworkProfile
    {
        if ($data['username'] ?? false) {
            $trashedNetworkProfile = $this->getByUsername(
                $data['username'],
                isset($data['network_source_id']) ? (int) $data['network_source_id'] : null
            );

            if ($trashedNetworkProfile) {
                $trashedNetworkProfile->restore();
                $trashedNetworkProfile->update($data);

                $this->syncNetworkTags(
                    $trashedNetworkProfile,
                    $data['tags'] ?? []
                );

                return $trashedNetworkProfile;
            }
        }

        $networkProfile = NetworkProfile::query()->create($data);

        $this->syncNetworkTags($networkProfile, $data['tags'] ?? []);

        return $networkProfile;
    }

    /**
     * Updates network profile with given data.
     */
    private function update(
        NetworkProfile $networkProfile,
        array $data
    ): NetworkProfile {
        $networkProfile->update($data);

        $this->syncNetworkTags($networkProfile, $data['tags'] ?? []);

        return $networkProfile;
    }

    /**
     * Sync network tags to network profile.
     */
    private function syncNetworkTags(
        NetworkProfile $networkProfile,
        array $networkTagIds
    ): void {
        /**
         * Method sync() removes missing IDs, attaches new ones,
         * and stays silent on existing ones.
         */
        $networkProfile->networkTags()->sync($networkTagIds);
    }

    /**
     * Filter query by whether description exists or not.
     */
    private function filterHasDescription(Builder $query, string $value): void
    {
        if ($value === '1') {
            $query->whereNotNull('description')->where('description', '<>', '');
        } elseif ($value === '0') {
            $query->where(function ($q): void {
                $q->whereNull('description')->orWhere('description', '');
            });
        }
    }

    /**
     * Filter query by search term in username, title, or description.
     *
     * @param  string|array<int, string>  $value
     */
    private function filterSearch(Builder $query, string|array $value): void
    {
        $this->applySearchFilter(
            $query,
            ['username', 'title', 'description'],
            $value
        );
    }

    /**
     * Filter query to exclude profiles that have ANY of the given tags.
     *
     * Uses the same withoutGlobalScopes() strategy as filterTags() for
     * accurate pivot-table lookups regardless of tag ownership.
     */
    private function filterExcludeTags(Builder $query, string|array $value): void
    {
        if (empty($value)) {
            return;
        }

        $ids = $this->normalizeTagIds($value);
        if (empty($ids)) {
            return;
        }

        // Exclude profiles that have ANY of the selected tags.
        $query->whereDoesntHave('networkTags', function ($q) use ($ids): void {
            $q->withoutGlobalScopes()
                ->whereIn('network_tags.id', $ids);
        });
    }

    /**
     * Filter query by associated network tags.
     *
     * Several branches below call withoutGlobalScopes() on the tag sub-query.
     * This is intentional: NetworkTag carries a UserScope that restricts records
     * to Auth::id(), but these sub-queries only test pivot-table existence —
     * no tag data is returned to the caller. Bypassing the scope gives accurate
     * results even when a profile is linked to a tag whose user_id differs from
     * the current user (e.g. after a reassignment or in tests). The outer
     * NetworkProfile query still has its own UserScope applied, so only the
     * current user's profiles are ever returned.
     */
    private function filterTags(Builder $query, string|array $value): void
    {
        if (empty($value)) {
            return;
        }

        if ($value === 'none' || (is_array($value) && in_array('none', $value))) {
            // Profiles that do not have any tags. Ignore tag global scopes so
            // tags owned by other users are considered when determining emptiness.
            $query->whereDoesntHave('networkTags', function ($q): void {
                $q->withoutGlobalScopes();
            });

            return;
        }

        if ($value === 'any' || (is_array($value) && in_array('any', $value))) {
            // Profiles that have at least one tag; ignore tag global scopes so tags
            // shared across users are considered
            $query->whereHas('networkTags', function ($q): void {
                $q->withoutGlobalScopes();
            });

            return;
        }

        $ids = $this->normalizeTagIds($value);
        if (empty($ids)) {
            return;
        }

        // Strict AND behaviour: return only profiles that have ALL selected tags.
        // withoutGlobalScopes() bypasses UserScope on NetworkTag so the pivot
        // lookup works correctly regardless of the tag's user_id. The requested
        // $ids constrain the results; no unrelated tag data is exposed.
        $query->whereHas('networkTags', function ($q) use ($ids): void {
            $q->withoutGlobalScopes()
                ->whereIn('network_tags.id', $ids);
        }, '=', count($ids));
    }

    /**
     * Normalize a tags filter value into a list of non-empty trimmed ID strings.
     *
     * Accepts either an array of values or a string (optionally comma-separated).
     * Tag IDs are ASCII numeric (network_tags.id), so trim() is sufficient.
     *
     * @return list<string>
     */
    private function normalizeTagIds(string|array $value): array
    {
        $ids = is_array($value)
            ? $value
            : (str_contains($value, ',') ? explode(',', $value) : [$value]);

        return array_values(array_filter(
            array_map(fn ($v): string => trim((string) $v), $ids),
            fn ($v): bool => $v !== ''
        ));
    }
}
