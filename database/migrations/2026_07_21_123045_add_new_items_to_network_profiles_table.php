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
            $table->unsignedInteger('new_items')->default(0)->after('number_of_visits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(DatabaseTableNamesEnum::network_profiles->value, function (Blueprint $table): void {
            $table->dropColumn('new_items');
        });
    }
};
