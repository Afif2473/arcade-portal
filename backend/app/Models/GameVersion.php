<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameVersion extends Model
{
    use HasFactory;

    protected $appends = ['launch_url'];

    protected $fillable = [
        'game_id',
        'version',
        's3_prefix',
        'source',
        'status',
        'bundle_size',
        'file_count',
        'commit_sha',
        'failure_reason',
        'uploaded_by',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getLaunchUrlAttribute(): string
    {
        return rtrim(config('filesystems.game_cdn_url'), '/') . '/' . $this->s3_prefix . 'index.html';
    }

}
