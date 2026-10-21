<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStateRequest;
use App\Models\Game;
use App\Models\GameSave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameSaveController extends Controller
{
    public function show(Request $request, string $slug, int $slot = 0): JsonResponse
    {
        $game = Game::where('slug', $slug)->firstOrFail();

        $save = GameSave::where('game_id', $game->id)
            ->where('user_id', $request->user()->id)
            ->where('slot', $slot)
            ->first();

        return response()->json([
            'slot' => $slot,
            'data' => $save?->data,
            'revision' => $save?->revision ?? 0,
            'updated_at' => $save?->updated_at?->toIso8601String(),
        ]);
    }

    public function store(SaveStateRequest $request, string $slug): JsonResponse
    {
        $game = Game::where('slug', $slug)->firstOrFail();
        $slot = $request->input('slot', 0);
        $user = $request->user();

        $save = GameSave::firstOrNew([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'slot' => $slot,
        ]);

        // Optimistic concurrency check if revision is provided
        if ($save->exists && $request->has('revision')) {
            if ($request->input('revision') !== $save->revision) {
                return response()->json([
                    'message' => 'Conflict: Save state revision out of date.',
                    'current_revision' => $save->revision,
                ], 409);
            }
        }

        $save->data = $request->input('data');
        $save->revision = ($save->revision ?? 0) + 1;
        $save->save();

        return response()->json([
            'slot' => $slot,
            'revision' => $save->revision,
            'updated_at' => $save->updated_at->toIso8601String(),
        ]);
    }
}
