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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name', 128);
            $table->string('description', 280)->nullable();
            $table->string('icon_path', 512)->nullable();
            $table->unsignedSmallInteger('points')->default(10);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->unique(['game_id', 'key'], 'ach_game_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
