<?php

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @package App\Models
 *
 * @property int $number_of_visits
 * @property string $last_visit_at
 */
class NetworkProfile extends Model
{
    use SoftDeletes, HasFactory;

    /**
     * Allowed includes to use as relationships.
     *
     * @var array
     */
    public const ALLOWED_INCLUDES = [
        'user',
        'networkSource',
    ];

    /**
     * Allowed filters to use to filter model's data.
     *
     * @var array
     */
    public const ALLOWED_FILTERS = [
        'is_public',
        'is_favorite',
        'network_source_id',
    ];

    /**
     * @var list<string>
     */
    public $fillable = [
        'user_id',
        'network_source_id',
        'username',
        'is_public',
        'is_favorite',
        'number_of_visits',
        'last_visited_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_favorite' => 'boolean',
        ];
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new UserScope);
    }

    /**
     * @return self
     */
    public function incrementNumberOfVisits(): self
    {
        $this->number_of_visits += 1;

        return $this;
    }

    /**
     * @return self
     */
    public function setLastVisitAt(): self
    {
        $this->last_visit_at = now()->toDateTimeString();

        return $this;
    }

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo
     */
    public function networkSource(): BelongsTo
    {
        return $this->belongsTo(NetworkSource::class);
    }
}
