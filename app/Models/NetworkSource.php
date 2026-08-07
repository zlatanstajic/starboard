<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NetworkSourcesEnum;
use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property string $url
 * @property ?string $icon
 */
class NetworkSource extends Model
{
    /** @use HasFactory<\Database\Factories\NetworkSourceFactory> */
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

        static::saving(function (NetworkSource $networkSource): void {
            if ($networkSource->isDirty('url') || ! $networkSource->exists) {
                $networkSource->icon = NetworkSourcesEnum::fromUrl($networkSource->url ?? '')?->value;
            }
        });

        static::updated(function (NetworkSource $networkSource): void {
            if ($networkSource->wasChanged('url')) {
                NetworkProfile::query()->withoutGlobalScopes()
                    ->where('network_source_id', $networkSource->id)
                    ->update(['youtube_channel_id' => null]);
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
}
