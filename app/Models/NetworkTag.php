<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class NetworkTag extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Allowed sorts to use to sort model's data.
     */
    public const array ALLOWED_SORTS = [
        'name',
        'description',
        'network_profiles_count',
        'created_at',
        'updated_at',
    ];

    /**
     * Fillable fields in this model.
     */
    public $fillable = [
        'user_id',
        'name',
        'description',
    ];

    /**
     * Gets network profiles associated with tag.
     */
    public function networkProfiles(): BelongsToMany
    {
        return $this->belongsToMany(NetworkProfile::class);
    }

    /**
     * The "booted" method of the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::addGlobalScope(new UserScope);
        static::deleting(function (NetworkTag $tag): void {
            if ($tag->isForceDeleting()) {
                $tag->networkProfiles()->detach();
            }
        });
    }

    /**
     * Filter which scopes query by the number of associated network profiles.
     *
     * The ranges mirror the dashboard's visits filter, with an extra `0` for
     * tags that have no profiles at all.
     */
    #[Scope]
    protected function byProfiles(Builder $query, ?string $range): Builder
    {
        return match ($range) {
            '0' => $query->doesntHave('networkProfiles'),
            '1-5' => $this->whereProfilesBetween($query, 1, 5),
            '6-10' => $this->whereProfilesBetween($query, 6, 10),
            '11-20' => $this->whereProfilesBetween($query, 11, 20),
            '21-50' => $this->whereProfilesBetween($query, 21, 50),
            '51-100' => $this->whereProfilesBetween($query, 51, 100),
            '100+' => $query->has('networkProfiles', '>', 100),
            default => $query,
        };
    }

    /**
     * Constrains the profile count to an inclusive range.
     */
    protected function whereProfilesBetween(Builder $query, int $from, int $to): Builder
    {
        return $query->has('networkProfiles', '>=', $from)
            ->has('networkProfiles', '<=', $to);
    }
}
