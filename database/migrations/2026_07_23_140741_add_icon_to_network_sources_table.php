<?php

declare(strict_types=1);

use App\Enums\DatabaseTableNamesEnum;
use App\Enums\NetworkSourcesEnum;
use App\Models\NetworkSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(DatabaseTableNamesEnum::network_sources->value, function (Blueprint $table) {
            $table->string('icon', 30)->nullable()->after('url');
        });

        NetworkSource::withoutGlobalScopes()
            ->get(['id', 'url'])
            ->each(function (NetworkSource $networkSource): void {
                NetworkSource::withoutGlobalScopes()
                    ->whereKey($networkSource->getKey())
                    ->toBase()
                    ->update([
                        'icon' => NetworkSourcesEnum::fromUrl((string) $networkSource->url)?->value,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(DatabaseTableNamesEnum::network_sources->value, function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
