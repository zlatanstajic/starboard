<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Override;

/**
 * User model representing application users.
 *
 *
 * @property int $id
 * @property string $password
 * @property string $email
 * @property string $email_verified_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * User has many network profiles.
     */
    public function networkProfiles(): HasMany
    {
        return $this->hasMany(NetworkProfile::class);
    }

    /**
     * User has many network sources.
     */
    public function networkSources(): HasMany
    {
        return $this->hasMany(NetworkSource::class);
    }

    /**
     * User has many network tags.
     */
    public function networkTags(): HasMany
    {
        return $this->hasMany(NetworkTag::class);
    }

    /**
     * Booted method of the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::deleted(function (User $user): void {
            $user->networkProfiles()->delete();
            $user->networkSources()->delete();
            $user->networkTags()->delete();
        });
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
