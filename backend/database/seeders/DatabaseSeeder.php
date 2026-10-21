<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create a developer user
        $developer = User::create([
            'username' => 'testdev',
            'email' => 'dev@example.com',
            'password' => bcrypt('password123'),
            'role' => 'developer',
        ]);

        // 2. Create a player user
        $player = User::create([
            'username' => 'speedrunner99',
            'email' => 'player@example.com',
            'password' => bcrypt('password123'),
            'role' => 'player',
        ]);

        // 3. Create a test game
        $game = Game::create([
            'developer_id' => $developer->id,
            'slug' => 'test-harness',
            'title' => 'Test Harness Game',
            'short_description' => 'A lightweight game to verify SDK integration.',
            'description' => 'Used for testing iframe postMessage, score reporting, and cloud saves.',
            'aspect_ratio' => '16:9',
            'status' => 'published',
            'score_sort' => 'desc',
            'published_at' => now(),
        ]);

        // 4. Create version 1
        $version = GameVersion::create([
            'game_id' => $game->id,
            'version' => 1,
            's3_prefix' => 'games/test-harness/v1/',
            'source' => 'zip_upload',
            'status' => 'ready',
            'uploaded_by' => $developer->id,
        ]);

        $game->update(['active_version_id' => $version->id]);
    }
}
