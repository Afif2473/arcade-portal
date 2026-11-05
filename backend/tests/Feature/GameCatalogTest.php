<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_only_displays_published_games(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);

        // Published Game
        Game::create([
            'developer_id' => $dev->id,
            'title' => 'Retro Blaster',
            'slug' => 'retro-blaster',
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Draft Game (Hidden)
        Game::create([
            'developer_id' => $dev->id,
            'title' => 'Secret Project',
            'slug' => 'secret-project',
            'status' => 'draft',
        ]);

        // Sandbox Game (Hidden from store)
        Game::create([
            'developer_id' => $dev->id,
            'title' => 'Staging Beta',
            'slug' => 'staging-beta',
            'status' => 'sandbox',
        ]);

        $response = $this->getJson('/api/games');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'retro-blaster');
    }

    public function test_can_view_single_game_details_by_slug(): void
    {
        $dev = User::factory()->create(['role' => 'developer', 'username' => 'pixelartist']);

        Game::create([
            'developer_id' => $dev->id,
            'title' => 'Space Odyssey',
            'slug' => 'space-odyssey',
            'short_description' => 'Defend the galaxy.',
            'aspect_ratio' => '16:9',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/games/space-odyssey');

        $response->assertStatus(200)
            ->assertJsonPath('title', 'Space Odyssey')
            ->assertJsonPath('developer.username', 'pixelartist');
    }
}