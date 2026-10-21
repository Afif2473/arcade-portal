<?php

namespace App\Http\Controllers\Api;

use App\Actions\SubmitGameScoreAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitScoreRequest;
use App\Http\Resources\LeaderboardResource;
use App\Http\Resources\ScoreSubmissionResource;
use App\Models\Game;
use App\Models\LeaderboardEntry;
use Illuminate\Http\JsonResponse;

class GameScoreController extends Controller
{
    public function submit(SubmitScoreRequest $request, string $slug, SubmitGameScoreAction $action): JsonResponse
    {
        $game = Game::where('slug', $slug)->firstOrFail();

        // Enforce sandbox visibility: sandbox games only accept scores from the creator/admin
        if ($game->status === 'sandbox' && $request->user()->id !== $game->developer_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Game is in sandbox mode.'], 403);
        }

        if ($game->status === 'draft') {
            return response()->json(['message' => 'Game is not published.'], 403);
        }

        $result = $action->execute(
            game: $game,
            user: $request->user(),
            scoreValue: $request->input('score'),
            metadata: $request->input('metadata'),
            ipAddress: $request->ip()
        );

        return response()->json(new ScoreSubmissionResource($result));
    }

    public function leaderboard(string $slug): JsonResponse
    {
        $game = Game::where('slug', $slug)->firstOrFail();

        $query = LeaderboardEntry::with(['user:id,username,avatar_path'])
            ->where('game_id', $game->id);

        if ($game->score_sort === 'asc') {
            $query->orderBy('best_score', 'asc')->orderBy('achieved_at', 'asc');
        } else {
            $query->orderBy('best_score', 'desc')->orderBy('achieved_at', 'asc');
        }

        $entries = $query->limit(50)->get();

        return response()->json([
            'data' => LeaderboardResource::collection($entries),
        ]);
    }
}
