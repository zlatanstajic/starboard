<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

abstract class Repository
{
    /**
     * The character escaping LIKE wildcards in search patterns.
     */
    private const string LIKE_ESCAPE_CHARACTER = '!';

    /**
     * Number of items displayed on single page.
     */
    protected int $itemsPerPage = 10;

    /**
     * Applies a "contains" search over the given columns, grouped in a single
     * closure so the OR cannot break out of the constraints (UserScope
     * included) already applied to the outer query.
     *
     * Spatie explodes a comma-bearing filter value into an array, so the term
     * is joined back together before matching.
     *
     * MySQL treats a backslash as the default LIKE escape character but SQLite
     * has none, so the wildcards are escaped with `!` and paired with an
     * explicit ESCAPE clause, which keeps `%` and `_` literal on both drivers.
     * The escape character itself is escaped first so it cannot cancel out the
     * wildcard escapes that follow it.
     *
     * @param  list<string>  $columns
     * @param  string|array<int, string>  $value
     */
    protected function applySearchFilter(
        EloquentBuilder|DatabaseBuilder $query,
        array $columns,
        string|array $value
    ): void {
        $term = is_array($value) ? implode(',', $value) : $value;

        $escaped = str_replace(
            [self::LIKE_ESCAPE_CHARACTER, '%', '_'],
            [
                self::LIKE_ESCAPE_CHARACTER.self::LIKE_ESCAPE_CHARACTER,
                self::LIKE_ESCAPE_CHARACTER.'%',
                self::LIKE_ESCAPE_CHARACTER.'_',
            ],
            $term
        );

        $query->where(function (EloquentBuilder|DatabaseBuilder $query) use ($columns, $escaped): void {
            foreach ($columns as $column) {
                $query->orWhereRaw(
                    $query->getGrammar()->wrap($column)
                        ." like ? escape '".self::LIKE_ESCAPE_CHARACTER."'",
                    ["%{$escaped}%"]
                );
            }
        });
    }

    /**
     * Standardizes the creation of a Spatie QueryBuilder instance for a given Model.
     */
    protected function buildStandardQuery(
        string $modelClass,
        array $includes = [],
        array $filters = [],
        array $sorts = []
    ): QueryBuilder {
        $query = QueryBuilder::for($modelClass);

        if (! empty($modelClass::ALLOWED_INCLUDES)) {
            $query->allowedIncludes(
                ...$modelClass::ALLOWED_INCLUDES,
                ...$includes
            );
        }

        $query = $this->applyNormalizedFilters($query, $modelClass, $filters);

        if (! empty($modelClass::ALLOWED_SORTS)) {
            $query->allowedSorts(
                ...$modelClass::ALLOWED_SORTS,
                ...$sorts
            );
        }

        return $query;
    }

    /**
     * Merge model-defined filters with additional filters and normalize them.
     * Converts string filter names that look like foreign keys (end with `_id`
     * or equal `id`) into `AllowedFilter::exact(...)` to avoid partial matching.
     */
    private function applyNormalizedFilters(
        QueryBuilder $query,
        string $modelClass,
        array $filters = []
    ): QueryBuilder {
        $mergedFilters = [];

        if (! empty($modelClass::ALLOWED_FILTERS)) {
            $mergedFilters = array_merge($modelClass::ALLOWED_FILTERS, $filters);
        } else {
            $mergedFilters = $filters;
        }

        if (! empty($mergedFilters)) {
            $normalized = [];

            foreach ($mergedFilters as $filter) {
                if (is_string($filter) && preg_match('/(^id$|_id$)/', $filter)) {
                    $normalized[] = AllowedFilter::exact($filter);

                    continue;
                }

                $normalized[] = $filter;
            }

            $query->allowedFilters(...$normalized);
        }

        return $query;
    }
}
