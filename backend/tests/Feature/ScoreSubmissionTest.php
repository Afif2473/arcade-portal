<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\LeaderboardEntry;
use App\Models\Score;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_player_can_submit_score_and_update_leaderboard(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $player = User::factory()->create(['role' => 'player']);

        $game = Game::create([
            'developer_id' => $dev->id,
            'title' => 'Asteroid Run',
            'slug' => 'asteroid-run',
            'status' => 'published',
            'score_sort' => 'desc',
            'published_at' => now(),
        ]);

        // Submit first score: 1000
        $res1 = $this->actingAs($player, 'sanctum')->postJson("/api/games/{$game->slug}/scores", [
            'score' => 1000,
            'metadata' => ['level' => 1],
        ]);

        $res1->assertStatus(200)
            ->assertJsonPath('personal_best', 1000)
            ->assertJsonPath('is_new_best', true);

        // Verify audit log row created in scores table
        $this->assertDatabaseHas('scores', ['game_id' => $game->id, 'score' => 1000]);

        // Submit lower score: 500 (Personal best should remain 1000)
        $res2 = $this->actingAs($player, 'sanctum')->postJson("/api/games/{$game->slug}/scores", [
            'score' => 500,
        ]);

        $res2->assertStatus(200)
            ->assertJsonPath('personal_best', 1000)
            ->assertJsonPath('is_new_best', false);

        // Submit higher score: 2500 (Personal best should update to 2500)
        $res3 = $this->actingAs($player, 'sanctum')->postJson("/api/games/{$game->slug}/scores", [
            'score' => 2500,
        ]);

        $res3->assertStatus(200)
            ->assertJsonPath('personal_best', 2500)
            ->assertJsonPath('is_new_best', true);

        $this->assertEquals(2500, LeaderboardEntry::where('game_id', $game->id)->where('user_id', $player->id)->first()->best_score);
    }

    public function test_unauthenticated_user_cannot_submit_score(): void
    {
        $game = Game::create([
            'developer_id' => User::factory()->create()->id,
            'title' => 'Locked Game',
            'slug' => 'locked-game',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->postJson("/api/games/{$game->slug}/scores", ['score' => 100]);
        $response->assertStatus(401);
    }
}