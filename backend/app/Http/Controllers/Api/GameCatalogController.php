<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class GameCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $games = Game::with(['developer:id,username'])
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->paginate(24);

        return response()->json($games);
    }

    public function show(string $slug): JsonResponse
    {
        $game = Game::with(['developer:id,username', 'activeVersion'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json($game);
    }
}
