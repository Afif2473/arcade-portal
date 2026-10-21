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
        Schema::create('game_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('slot')->default(0);
            $table->json('data');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['game_id', 'user_id', 'slot'], 'saves_game_user_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_saves');
    }
};
