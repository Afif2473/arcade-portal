<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaderboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'avatar_path' => $this->user->avatar_path,
            ],
            'best_score' => $this->best_score,
            'achieved_at' => $this->achieved_at->toIso8601String(),
        ];
    }
}
