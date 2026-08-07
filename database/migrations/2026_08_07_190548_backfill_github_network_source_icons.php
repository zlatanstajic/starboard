<?php

declare(strict_types=1);

use App\Enums\NetworkSourcesEnum;
use App\Models\NetworkSource;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill GitHub icons for every matching source, regardless of its current icon.
     */
    public function up(): void
    {
        NetworkSource::query()->withoutGlobalScopes()
            ->get(['id', 'url'])
            ->each(function (NetworkSource $networkSource): void {
                if (NetworkSourcesEnum::fromUrl((string) $networkSource->url) !== NetworkSourcesEnum::GitHub) {
                    return;
                }

                NetworkSource::query()->withoutGlobalScopes()
                    ->whereKey($networkSource->getKey())
                    ->toBase()
                    ->update(['icon' => NetworkSourcesEnum::GitHub->value]);
            });
    }

    /**
     * No-op: this migration only backfills data and drops no schema.
     */
    public function down(): void
    {
        //
    }
};
