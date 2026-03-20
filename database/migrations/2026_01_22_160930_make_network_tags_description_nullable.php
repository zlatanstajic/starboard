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
        Schema::table(DatabaseTableNamesEnum::network_tags->value, function (Blueprint $table) {
            $table->string('description', 150)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(DatabaseTableNamesEnum::network_tags->value, function (Blueprint $table) {
            $table->string('description', 150)->nullable(false)->change();
        });
    }
};
