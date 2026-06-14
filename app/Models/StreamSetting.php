<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StreamSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'youtube_key',
        'last_playlist_id',
        'last_video_ids',
        'is_active'
    ];

    protected $casts = [
        'last_video_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lastPlaylist()
    {
        return $this->belongsTo(Playlist::class, 'last_playlist_id');
    }
}
