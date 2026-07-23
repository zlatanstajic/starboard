<?php

declare(strict_types=1);

use App\Enums\NetworkSourcesEnum;
use App\Models\NetworkSource;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Re-derives the icon for sources that currently have none, so platforms
     * added after the original backfill (e.g. Google Sheets, Loom, Wikipedia)
     * are resolved for existing rows.
     */
    public function up(): void
    {
        NetworkSource::withoutGlobalScopes()
            ->whereNull('icon')
            ->get(['id', 'url'])
            ->each(function (NetworkSource $networkSource): void {
                $icon = NetworkSourcesEnum::fromUrl((string) $networkSource->url)?->value;

                if ($icon === null) {
                    return;
                }

                NetworkSource::withoutGlobalScopes()
                    ->whereKey($networkSource->getKey())
                    ->toBase()
                    ->update(['icon' => $icon]);
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
