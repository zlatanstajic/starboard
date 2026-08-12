<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $hash
 * @property ?string $description
 * @property array{filter?: array<string, mixed>, sort?: string} $filters
 * @property bool $is_published
 * @property ?Carbon $published_at
 */
class FilterList extends Model
{
    use HasFactory, SoftDeletes;

    public const array ALLOWED_SORTS = [
        'name',
        'is_published',
        'created_at',
        'updated_at',
    ];

    #[Override]
    public $fillable = [
        'user_id',
        'name',
        'hash',
        'description',
        'filters',
        'is_published',
        'published_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicUrl(): string
    {
        return route('filter-lists.show', $this->hash);
    }

    #[Override]
    protected static function booted(): void
    {
        static::addGlobalScope(new UserScope);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
