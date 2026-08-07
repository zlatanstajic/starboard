<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\FilterList\FilterListHashGenerationException;
use App\Models\FilterList;
use App\Models\Scopes\UserScope;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;

class FilterListRepository extends Repository
{
    private const int HASH_ATTEMPTS = 10;

    public function getAll(
        bool $paginate = false,
        string $defaultSort = '-created_at',
        bool $filterable = false
    ): LengthAwarePaginator {
        $query = $filterable
            ? $this->buildStandardQuery(
                FilterList::class,
                filters: $this->additionalAllowedFilters()
            )->defaultSort($defaultSort)
            : FilterList::query()->orderBy(
                ltrim($defaultSort, '-'),
                str_starts_with($defaultSort, '-') ? 'desc' : 'asc'
            );

        if (! $paginate) {
            $items = $query->get();
            $count = $items->count();

            return new LengthAwarePaginator($items, $count, max($count, 1), 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return $query->paginate($this->itemsPerPage)->withQueryString();
    }

    public function upsert(array $data, ?FilterList $filterList = null): FilterList
    {
        return $this->translatingHashCollision(fn (): FilterList => $filterList
            ? $this->update($filterList, $data)
            : $this->create($data));
    }

    public function delete(int $id): bool
    {
        return (bool) FilterList::destroy($id);
    }

    public function getPublishedByHash(string $hash): ?FilterList
    {
        return FilterList::query()
            ->withoutGlobalScope(UserScope::class)
            ->where('hash', $hash)
            ->where('is_published', true)
            ->first();
    }

    /**
     * Newest published lists across every user, for the public landing page.
     * published_at is nullable, so id breaks ties deterministically.
     *
     * @return Collection<int, FilterList>
     */
    public function getLatestPublished(int $limit): Collection
    {
        return FilterList::query()
            ->withoutGlobalScope(UserScope::class)
            ->where('is_published', true)
            ->latest('published_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * published_at records the first publication and is never rewritten: a
     * republish must not buy a fresh ranking on the public landing page, and
     * is_published alone gates visibility while unpublished.
     */
    public function setPublished(
        FilterList $filterList,
        bool $published,
        ?string $freshHash = null
    ): FilterList {
        $data = [
            'is_published' => $published,
            'published_at' => $filterList->published_at ?? now(),
        ];

        if ($freshHash !== null) {
            $data['hash'] = $freshHash;
        }

        return $this->translatingHashCollision(
            fn (): FilterList => $this->update($filterList, $data)
        );
    }

    public function mintHash(): string
    {
        for ($attempt = 0; $attempt < self::HASH_ATTEMPTS; $attempt++) {
            $hash = Str::random(12);

            if (! FilterList::query()
                ->withoutGlobalScope(UserScope::class)
                ->withTrashed()
                ->where('hash', $hash)
                ->exists()) {
                return $hash;
            }
        }

        throw new FilterListHashGenerationException;
    }

    /** @return list<AllowedFilter> */
    private function additionalAllowedFilters(): array
    {
        return [
            AllowedFilter::callback('search', $this->filterSearch(...)),
            AllowedFilter::exact('is_published'),
        ];
    }

    /**
     * Runs a write, translating a hash unique-constraint violation into the
     * domain exception. filter_lists.hash is the table's only unique index
     * (soft-delete inclusive), so a 23000 can only mean a burned hash.
     *
     * @param  callable(): FilterList  $write
     *
     * @throws FilterListHashGenerationException
     */
    private function translatingHashCollision(callable $write): FilterList
    {
        try {
            return $write();
        } catch (Exception $e) {
            throw_if((string) $e->getCode() === '23000', FilterListHashGenerationException::class);

            throw $e;
        }
    }

    private function create(array $data): FilterList
    {
        $published = (bool) ($data['is_published'] ?? false);

        return FilterList::query()->create(array_merge($data, [
            'hash' => $this->mintHash(),
            'is_published' => $published,
            'published_at' => $published ? now() : null,
        ]));
    }

    private function update(FilterList $filterList, array $data): FilterList
    {
        $filterList->update($data);

        return $filterList->refresh();
    }

    private function filterSearch(Builder $query, string|array $value): void
    {
        $this->applySearchFilter($query, ['name', 'description'], $value);
    }
}
