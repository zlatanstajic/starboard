<?php

declare(strict_types=1);

use App\Enums\DatabaseTableNamesEnum;
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
        Schema::table(DatabaseTableNamesEnum::network_profiles->value, function (Blueprint $table): void {
            $table->string('youtube_channel_id')->nullable()->after('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(DatabaseTableNamesEnum::network_profiles->value, function (Blueprint $table): void {
            $table->dropColumn('youtube_channel_id');
        });
    }
};
