<?php

declare(strict_types=1);

use App\Enums\DatabaseTableNamesEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(DatabaseTableNamesEnum::filter_lists->value, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained(DatabaseTableNamesEnum::users->value)
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('hash', 16)->unique();
            $table->text('description')->nullable();
            $table->json('filters');
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseTableNamesEnum::filter_lists->value);
    }
};
