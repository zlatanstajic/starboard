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
        Schema::create(DatabaseTableNamesEnum::network_tags->value, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained(DatabaseTableNamesEnum::users->value)
                ->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('description', 150);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'name']);
        });

        Schema::create(DatabaseTableNamesEnum::network_profile_network_tag->value, function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_profile_id')
                ->constrained(DatabaseTableNamesEnum::network_profiles->value)
                ->cascadeOnDelete();
            $table->foreignId('network_tag_id')
                ->constrained(DatabaseTableNamesEnum::network_tags->value)
                ->cascadeOnDelete();

            $table->unique(['network_profile_id', 'network_tag_id'])
                ->name('unique_network_profile_network_tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(DatabaseTableNamesEnum::network_profile_network_tag->value);
        Schema::dropIfExists(DatabaseTableNamesEnum::network_tags->value);
    }
};
