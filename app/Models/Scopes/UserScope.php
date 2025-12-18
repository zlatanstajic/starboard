<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * UserScope
 *
 * A global scope to restrict queries to the authenticated user's records.
 *
 * @package App\Models\Scopes
 */
final class UserScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Builder $builder
     * @param Model $model
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check()) {
            $builder->where('user_id', Auth::id());
        }
    }
}
