<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Http\Request;
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
        array $sorts = [],
        ?EloquentBuilder $subject = null,
        ?Request $request = null,
        bool $includeModelFilters = true,
        bool $includeModelIncludes = true,
        bool $includeModelSorts = true
    ): QueryBuilder {
        $query = QueryBuilder::for($subject ?? $modelClass, $request);
        $allowedIncludes = $includeModelIncludes
            ? array_merge($modelClass::ALLOWED_INCLUDES, $includes)
            : $includes;

        if ($allowedIncludes !== []) {
            $query->allowedIncludes(...$allowedIncludes);
        }

        $query = $this->applyNormalizedFilters($query, $modelClass, $filters, $includeModelFilters);

        $allowedSorts = $includeModelSorts
            ? array_merge($modelClass::ALLOWED_SORTS, $sorts)
            : $sorts;

        if ($allowedSorts !== []) {
            $query->allowedSorts(...$allowedSorts);
        }

        return $query;
    }

    /**
     * Starts a public read with the tenant scope removed and an explicit owner
     * constraint restored. Public callers must use this helper so UserScope's
     * guest fail-open behavior can never expose another user's records.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @return EloquentBuilder<TModel>
     */
    protected function ownerScopedQuery(EloquentBuilder $query, int $ownerId): EloquentBuilder
    {
        $this->constrainToOwner($query, $query->getModel()::class, $ownerId);

        return $query;
    }

    /**
     * Applies the same fail-closed owner constraint to an existing relation or
     * Eloquent query, including relationship eager-load queries.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function constrainToOwner(
        EloquentBuilder|Relation $query,
        string $modelClass,
        int $ownerId
    ): void {
        $model = new $modelClass;
        $builder = $query instanceof Relation ? $query->getQuery() : $query;

        $builder
            ->withoutGlobalScope(UserScope::class)
            ->where($model->getTable().'.user_id', $ownerId);
    }

    /**
     * Merge model-defined filters with additional filters and normalize them.
     * Converts string filter names that look like foreign keys (end with `_id`
     * or equal `id`) into `AllowedFilter::exact(...)` to avoid partial matching.
     */
    private function applyNormalizedFilters(
        QueryBuilder $query,
        string $modelClass,
        array $filters = [],
        bool $includeModelFilters = true
    ): QueryBuilder {
        $mergedFilters = [];

        if ($includeModelFilters && ! empty($modelClass::ALLOWED_FILTERS)) {
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
