<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NetworkProfile\NetworkProfileDuplicationException;
use App\Models\NetworkProfile;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Spatie\QueryBuilder\AllowedFilter;

class NetworkProfileRepository extends Repository
{
    /**
     * Gets all network profile
     */
    public function getAll(
        array $includes = [
            'networkSource',
            'user',
            'networkTags',
        ],
        string $defaultSort = 'last_visit_at'
    ): LengthAwarePaginator {
        $query = $this->buildStandardQuery(
            NetworkProfile::class,
            filters: $this->additionalAllowedFilters()
        )
            ->defaultSort($defaultSort);

        // If no specific network source was selected (i.e. "All Network Sources"),
        // exclude profiles that belong to network sources marked as excluded.
        if (! request('filter.network_source_id')) {
            $query->whereHas('networkSource', function ($q): void {
                // Ignore global scopes (like UserScope) on the NetworkSource
                // subquery so tests that create sources with different owners
                // still match when evaluating the exclude flag.
                $q->withoutGlobalScopes()->where('exclude_from_dashboard', false);
            });
        }

        return $query->with($includes)
            ->paginate($this->itemsPerPage)
            ->withQueryString();
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
