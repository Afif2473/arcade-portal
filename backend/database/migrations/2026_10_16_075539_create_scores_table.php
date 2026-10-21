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
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('game_version_id')->nullable()->constrained('game_versions')->nullOnDelete();
            $table->bigInteger('score');
            $table->json('metadata')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['game_id', 'score', 'created_at'], 'scores_leaderboard_idx');
            $table->index(['user_id', 'game_id', 'created_at'], 'scores_user_game_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
