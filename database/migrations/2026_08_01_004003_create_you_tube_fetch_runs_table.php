<?php

declare(strict_types=1);

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
        Schema::create('youtube_fetch_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('youtube_fetch_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('network_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 32)->default('queued');
            $table->string('outcome', 64)->nullable()->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('transport', 32)->nullable();
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('retry_delay_seconds')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_fetch_runs');
    }
};
