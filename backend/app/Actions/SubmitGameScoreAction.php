<?php

namespace App\Actions;

use App\Models\Game;
use App\Models\LeaderboardEntry;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitGameScoreAction
{
    public function execute(Game $game, User $user, int $scoreValue, ?array $metadata = null, ?string $ipAddress = null): array
    {
        return DB::transaction(function () use ($game, $user, $scoreValue, $metadata, $ipAddress) {
            // 1. Record the historical score entry
            $score = Score::create([
                'game_id' => $game->id,
                'user_id' => $user->id,
                'game_version_id' => $game->active_version_id,
                'score' => $scoreValue,
                'metadata' => $metadata,
                'ip_hash' => $ipAddress ? hash('sha256', $ipAddress) : null,
                'created_at' => now(),
            ]);

            // 2. Determine if it is a personal best
            $entry = LeaderboardEntry::where('game_id', $game->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $isAscending = $game->score_sort === 'asc';
            $isNewBest = false;

            if (! $entry) {
                $isNewBest = true;
                $entry = LeaderboardEntry::create([
                    'game_id' => $game->id,
                    'user_id' => $user->id,
                    'best_score' => $scoreValue,
                    'best_score_id' => $score->id,
                    'achieved_at' => now(),
                ]);
            } else {
                $improved = $isAscending
                    ? $scoreValue < $entry->best_score
                    : $scoreValue > $entry->best_score;

                if ($improved) {
                    $isNewBest = true;
                    $entry->update([
                        'best_score' => $scoreValue,
                        'best_score_id' => $score->id,
                        'achieved_at' => now(),
                    ]);
                }
            }

            // 3. Compute player's rank using composite index
            $rankQuery = LeaderboardEntry::where('game_id', $game->id);
            if ($isAscending) {
                $rank = $rankQuery->where(function ($q) use ($entry) {
                    $q->where('best_score', '<', $entry->best_score)
                      ->orWhere(function ($sub) use ($entry) {
                          $sub->where('best_score', '=', $entry->best_score)
                              ->where('achieved_at', '<', $entry->achieved_at);
                      });
                })->count() + 1;
            } else {
                $rank = $rankQuery->where(function ($q) use ($entry) {
                    $q->where('best_score', '>', $entry->best_score)
                      ->orWhere(function ($sub) use ($entry) {
                          $sub->where('best_score', '=', $entry->best_score)
                              ->where('achieved_at', '<', $entry->achieved_at);
                      });
                })->count() + 1;
            }

            return [
                'score' => $scoreValue,
                'personal_best' => $entry->best_score,
                'rank' => $rank,
                'is_new_best' => $isNewBest,
            ];
        });
    }
}
