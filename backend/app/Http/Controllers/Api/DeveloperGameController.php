<?php

namespace App\Http\Controllers\Api;

use App\Actions\ProcessGameZipUploadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadGameZipRequest;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperGameController extends Controller
{
    /**
     * List all games created by the authenticated developer
     */
    public function index(Request $request): JsonResponse
    {
        $games = Game::with(['activeVersion', 'versions' => function ($q) {
            $q->orderBy('version', 'desc');
        }])
        ->where('developer_id', $request->user()->id)
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json(['data' => $games]);
    }

    public function store(Request $request): JsonResponse
    {
        // Enforce developer or admin role
        if ($request->user()->role !== 'developer' && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized: Developer role required.'], 403);
        }
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:128'],
            'slug' => ['required', 'string', 'max:64', 'unique:games,slug', 'regex:/^[a-z0-9\-]+$/'],
            'short_description' => ['nullable', 'string', 'max:280'],
            'description' => ['nullable', 'string'],
            'aspect_ratio' => ['nullable', 'in:16:9,4:3,9:16'],
            'score_sort' => ['nullable', 'in:desc,asc'],
        ]);
        $game = Game::create([
            'developer_id' => $request->user()->id,
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'short_description' => $validated['short_description'] ?? null,
            'description' => $validated['description'] ?? null,
            'aspect_ratio' => $validated['aspect_ratio'] ?? '16:9',
            'score_sort' => $validated['score_sort'] ?? 'desc',
            'status' => 'draft',
        ]);
        return response()->json([
            'message' => 'Game registered successfully.',
            'game' => $game,
        ], 201);
    }

    /**
     * Upload a new .zip release bundle for a game
     */
    public function uploadBundle(
        UploadGameZipRequest $request,
        string $slug,
        ProcessGameZipUploadAction $action
    ): JsonResponse {
        $game = Game::where('slug', $slug)->firstOrFail();

        // Ensure user is the game developer or an admin
        if ($request->user()->id !== $game->developer_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized: You do not own this game.'], 403);
        }

        $version = $action->execute(
            game: $game,
            zipFile: $request->file('bundle'),
            uploader: $request->user()
        );

        return response()->json([
            'message' => "Version {$version->version} uploaded successfully!",
            'version' => $version,
            'game_status' => $game->fresh()->status,
        ], 201);
    }

    /**
     * Update game metadata or lifecycle status (draft, sandbox, published)
     */
    public function updateStatus(Request $request, string $slug): JsonResponse
    {
        $game = Game::where('slug', $slug)->firstOrFail();

        if ($request->user()->id !== $game->developer_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized: You do not own this game.'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:draft,sandbox,published'],
            'aspect_ratio' => ['sometimes', 'in:16:9,4:3,9:16'],
            'title' => ['sometimes', 'string', 'max:128'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:280'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'published' && ! $game->published_at) {
            $validated['published_at'] = now();
        }

        $game->update($validated);

        return response()->json([
            'message' => 'Game updated successfully.',
            'game' => $game->fresh(),
        ]);
    }
}
