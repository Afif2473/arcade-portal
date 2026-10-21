<?php

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
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('best_score');
            $table->foreignId('best_score_id')->nullable()->constrained('scores')->nullOnDelete();
            $table->timestamp('achieved_at');
            $table->timestamps();

            $table->unique(['game_id', 'user_id'], 'lb_game_user_unique');
            $table->index(['game_id', 'best_score', 'achieved_at'], 'lb_rank_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
    }
};
