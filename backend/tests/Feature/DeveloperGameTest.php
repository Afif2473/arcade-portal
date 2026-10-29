<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_create_a_game(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);

        $response = $this->actingAs($dev, 'sanctum')->postJson('/api/developer/games', [
            'title' => 'Galactic Invaders',
            'slug' => 'galactic-invaders',
            'short_description' => 'A retro arcade shooter',
            'aspect_ratio' => '16:9',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('game.slug', 'galactic-invaders')
            ->assertJsonPath('game.status', 'draft');

        $this->assertDatabaseHas('games', [
            'slug' => 'galactic-invaders',
            'developer_id' => $dev->id,
        ]);
    }

    public function test_player_cannot_create_a_game(): void
    {
        $player = User::factory()->create(['role' => 'player']);

        $response = $this->actingAs($player, 'sanctum')->postJson('/api/developer/games', [
            'title' => 'Sneaky Player Game',
            'slug' => 'sneaky-game',
        ]);

        $response->assertStatus(403);
    }

    public function test_slug_must_be_unique(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        Game::create([
            'developer_id' => $dev->id,
            'title' => 'Existing Game',
            'slug' => 'existing-game',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($dev, 'sanctum')->postJson('/api/developer/games', [
            'title' => 'Duplicate Slug',
            'slug' => 'existing-game',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }
}
