<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NetworkSourcesEnum;
use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @property string $url
 * @property ?string $icon
 */
class NetworkSource extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Allowed sorts to use to sort model's data.
     */
    public const array ALLOWED_SORTS = [
        'name',
        'url',
        'exclude_from_dashboard',
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
        'url',
        'exclude_from_dashboard',
    ];

    /**
     * Attribute casting.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'exclude_from_dashboard' => 'boolean',
    ];

    /**
     * Get the network profiles for the user.
     */
    public function networkProfiles(): HasMany
    {
        return $this->hasMany(NetworkProfile::class);
    }

    /**
     * The "booted" method of the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::addGlobalScope(new UserScope);

        static::saving(function (NetworkSource $networkSource): void {
            if ($networkSource->isDirty('url') || ! $networkSource->exists) {
                $networkSource->icon = NetworkSourcesEnum::fromUrl($networkSource->url ?? '')?->value;
            }
        });

        static::deleted(function (NetworkSource $networkSource): void {
            $networkSource->networkProfiles()->delete();
        });

        static::restored(function (NetworkSource $networkSource): void {
            NetworkProfile::onlyTrashed()
                ->where('network_source_id', $networkSource->id)
                ->restore();
        });
    }

    /**
     * Filter which scopes query by the number of associated network profiles.
     *
     * The ranges mirror the dashboard's visits filter, with an extra `0` for
     * sources that have no profiles at all.
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
