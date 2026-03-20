<?php

declare(strict_types=1);

namespace App\Repositories;

use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

abstract class Repository
{
    /**
     * Number of items displayed on single page.
     */
    protected int $itemsPerPage = 10;

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
