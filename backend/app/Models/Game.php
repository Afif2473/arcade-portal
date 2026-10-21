<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Game extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'developer_id',
        'slug',
        'title',
        'short_description',
        'description',
        'thumbnail_path',
        'aspect_ratio',
        'status',
        'score_sort',
        'active_version_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function developer()
    {
        return $this->belongsTo(User::class, 'developer_id');
    }

    public function versions()
    {
        return $this->hasMany(GameVersion::class);
    }

    public function activeVersion()
    {
        return $this->belongsTo(GameVersion::class, 'active_version_id');
    }

    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    public function leaderboardEntries()
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    public function saves()
    {
        return $this->hasMany(GameSave::class);
    }

    public function achievements()
    {
        return $this->hasMany(Achievement::class);
    }
}
