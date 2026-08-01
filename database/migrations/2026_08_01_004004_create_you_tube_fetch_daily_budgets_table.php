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
        Schema::create('youtube_fetch_daily_budgets', function (Blueprint $table) {
            $table->id();
            $table->date('budget_date')->unique();
            $table->unsignedInteger('reserved_requests')->default(0);
            $table->timestamp('blocked_until')->nullable();
            $table->string('block_reason', 64)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_fetch_daily_budgets');
    }
};
