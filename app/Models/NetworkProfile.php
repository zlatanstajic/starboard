<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Override;

/**
 * @property int $id
 * @property string $username
 * @property int $number_of_visits
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $last_visit_at
 * @property-read NetworkSource|null $networkSource
 */
class NetworkProfile extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Allowed includes to use as relationships.
     */
    public const array  ALLOWED_INCLUDES = [
        'user',
        'networkSource',
        'networkTags',
    ];

    /**
     * Allowed filters to use to filter model's data.
     */
    public const array ALLOWED_FILTERS = [
        'is_public',
        'is_favorite',
        'network_source_id',
    ];

    /**
     * Allowed sorts to use to sort model's data.
     */
    public const array ALLOWED_SORTS = [
        'username',
        'number_of_visits',
        'last_visit_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Fillable fields in this model.
     */
    public $fillable = [
        'user_id',
        'network_source_id',
        'username',
        'title',
        'description',
        'is_public',
        'is_favorite',
        'number_of_visits',
        'last_visit_at',
    ];

    /**
     * Network profile belongs to one user only.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Network profile belongs to one network source only.
     */
    public function networkSource(): BelongsTo
    {
        return $this->belongsTo(NetworkSource::class);
    }

    /**
     * Gets network tags associated with profile.
     */
    public function networkTags(): BelongsToMany
    {
        return $this->belongsToMany(NetworkTag::class);
    }

    /**
     * Build the profile URL by replacing the placeholder
     * on the associated NetworkSource URL with this profile's username.
     */
    public function profileUrl(): string
    {
        $url = $this->networkSource?->url;

        if (! $url) {
            return '';
        }

        $placeholders = [
            '{username}',
            '{id}',
            '{hash}',
            '{uuid}',
        ];

        return Str::replace($placeholders, $this->username, $url);
    }

    /**
     * The "booted" method of the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::addGlobalScope(new UserScope);
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_favorite' => 'boolean',
            'last_visit_at' => 'datetime',
        ];
    }

    /**
     * Filter which scopes query by number_of_visits field.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byVisits($query, $range): mixed
    {
        return match ($range) {
            '1-5' => $query->whereBetween('number_of_visits', [1, 5]),
            '6-10' => $query->whereBetween('number_of_visits', [6, 10]),
            '11-20' => $query->whereBetween('number_of_visits', [11, 20]),
            '21-50' => $query->whereBetween('number_of_visits', [21, 50]),
            '51-100' => $query->whereBetween('number_of_visits', [51, 100]),
            '100+' => $query->where('number_of_visits', '>', 100),
            default => $query,
        };
    }

    /**
     * Filter which scopes query by last_visit_at field.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byLastVisit($query, $period): mixed
    {
        $now = now();

        $oneDayAgo = $now->copy()->subDay()->toDateTimeString();
        $sevenDaysAgo = $now->copy()->subDays(7)->toDateTimeString();
        $thirtyDaysAgo = $now->copy()->subDays(30)->toDateTimeString();

        return match ($period) {
            '24h' => $query->where('last_visit_at', '>=', $oneDayAgo),
            '7d' => $query->whereBetween('last_visit_at', [$sevenDaysAgo, $oneDayAgo]),
            '30d' => $query->whereBetween('last_visit_at', [$thirtyDaysAgo, $sevenDaysAgo]),
            'older' => $query->where('last_visit_at', '<', $thirtyDaysAgo),
            'not_24h' => $query->where('last_visit_at', '<', $oneDayAgo),
            default => $query,
        };
    }

    /**
     * Shortens last_visit_at parameter to be in human-readable format.
     */
    protected function lastVisitShort(): Attribute
    {
        return Attribute::get(fn () => $this->formatShortDate($this->last_visit_at));
    }
}
