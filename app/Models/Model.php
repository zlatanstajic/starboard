<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * @package App\Models
 */
abstract class Model extends EloquentModel
{
    /**
     * Allowed includes to use as relationships.
     *
     * @var array
     */
    public const ALLOWED_INCLUDES = [];

    /**
     * Allowed filters to use to filter model's data.
     *
     * @var array
     */
    public const ALLOWED_FILTERS = [];
}
