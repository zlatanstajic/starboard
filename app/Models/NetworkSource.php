<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @property string $url
 */
class NetworkSource extends Model
{
    use HasFactory, SoftDeletes;

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

        static::deleted(function (NetworkSource $networkSource): void {
            $networkSource->networkProfiles()->delete();
        });

        static::restored(function (NetworkSource $networkSource): void {
            NetworkProfile::onlyTrashed()
                ->where('network_source_id', $networkSource->id)
                ->restore();
        });
    }
}
