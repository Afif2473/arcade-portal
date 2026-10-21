<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScoreSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'score' => $this['score'],
            'personal_best' => $this['personal_best'],
            'rank' => $this['rank'],
            'is_new_best' => $this['is_new_best'],
        ];
    }
}
