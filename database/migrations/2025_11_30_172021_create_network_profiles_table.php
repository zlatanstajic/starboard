<?php

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
        Schema::create(DatabaseTableNamesEnum::network_profiles->value, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained(DatabaseTableNamesEnum::users->value)
                ->cascadeOnDelete();
            $table->foreignId('network_source_id')
                ->constrained(DatabaseTableNamesEnum::network_sources->value)
                ->cascadeOnDelete();
            $table->string('username', 100);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_favorite')->default(false);
            $table->integer('number_of_visits')->default(1);
            $table->timestamp('last_visit_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'network_source_id', 'username']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(DatabaseTableNamesEnum::network_profiles->value, function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('network_source_id');
        });

        Schema::dropIfExists(DatabaseTableNamesEnum::network_profiles->value);
    }
};
