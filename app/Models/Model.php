<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Date;

/**
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
abstract class Model extends EloquentModel
{
    /**
     * Allowed includes to use as relationships.
     */
    public const array ALLOWED_INCLUDES = [];

    /**
     * Allowed filters to use to filter model's data.
     */
    public const array ALLOWED_FILTERS = [];

    /**
     * Allowed sorts to use to sort model's data.
     */
    public const array ALLOWED_SORTS = [];

    /**
     * Reusable date shortener logic.
     */
    protected function formatShortDate($date): string
    {
        $carbonDate = is_string($date) ? Date::parse($date) : $date;

        $dateForHumans = $carbonDate->diffForHumans([
            'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
            'short' => true,
            'parts' => 1,
        ]);

        if (str_ends_with($dateForHumans, ' ago')) {
            $dateForHumans = mb_substr($dateForHumans, 0, -4);
        }

        return $dateForHumans;
    }

    /**
     * Shortens created_at parameter to be in human-readable format.
     */
    protected function createdAtShort(): Attribute
    {
        return Attribute::get(fn () => $this->formatShortDate($this->created_at));
    }

    /**
     * Shortens updated_at parameter to be in human-readable format.
     */
    protected function updatedAtShort(): Attribute
    {
        return Attribute::get(fn () => $this->formatShortDate($this->updated_at));
    }
}
