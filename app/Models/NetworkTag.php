<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class NetworkTag extends Model
{
    use HasFactory, SoftDeletes;

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
}
