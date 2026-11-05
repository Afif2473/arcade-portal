<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_save_and_retrieve_cloud_state(): void
    {
        $player = User::factory()->create();
        $game = Game::create([
            'developer_id' => User::factory()->create()->id,
            'title' => 'RPG Quest',
            'slug' => 'rpg-quest',
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Save state in slot 0
        $saveData = ['checkpoint' => 'castle_gate', 'coins' => 250, 'inventory' => ['sword', 'potion']];
        $storeRes = $this->actingAs($player, 'sanctum')->postJson("/api/games/{$game->slug}/saves", [
            'slot' => 0,
            'data' => $saveData,
        ]);

        $storeRes->assertStatus(200)
            ->assertJsonPath('slot', 0)
            ->assertJsonPath('revision', 1);

        // Retrieve saved state
        $getRes = $this->actingAs($player, 'sanctum')->getJson("/api/games/{$game->slug}/saves/0");

        $getRes->assertStatus(200)
            ->assertJsonPath('data.checkpoint', 'castle_gate')
            ->assertJsonPath('data.coins', 250)
            ->assertJsonPath('revision', 1);
    }

        public function test_detects_optimistic_concurrency_conflict(): void
    {
        $player = User::factory()->create();
        $game = Game::create([
            'developer_id' => User::factory()->create()->id,
            'title' => 'Speed Run',
            'slug' => 'speed-run',
            'status' => 'published',
            'published_at' => now(),
        ]);

        // First save creates revision 1
        $this->actingAs($player, 'sanctum')->postJson("/api/games/{$game->slug}/saves", [
            'slot' => 0,
            'data' => ['v' => 1],
        ]);

        // Second save advances server to revision 2
        $this->actingAs($player, 'sanctum')->postJson("/api/games/{$game->slug}/saves", [
            'slot' => 0,
            'revision' => 1,
            'data' => ['v' => 2],
        ]);

        // Client attempts to save with outdated revision 1 (when server is already at 2)
        $conflictRes = $this->actingAs($player, 'sanctum')->postJson("/api/games/{$game->slug}/saves", [
            'slot' => 0,
            'revision' => 1,
            'data' => ['v' => 3],
        ]);

        $conflictRes->assertStatus(409)
            ->assertJsonPath('current_revision', 2);
    }
}