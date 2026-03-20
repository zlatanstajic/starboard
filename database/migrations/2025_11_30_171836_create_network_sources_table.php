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
        Schema::create(DatabaseTableNamesEnum::network_sources->value, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained(DatabaseTableNamesEnum::users->value)
                ->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('url', 150);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'name', 'url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(DatabaseTableNamesEnum::network_sources->value);
    }
};
