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
        'google_email',
        'google_oauth_email',
        'google_oauth_refresh_token',
        'google_oauth_scopes',
        'google_oauth_connected_at',
        'youtube_channel_id',
        'youtube_cookie_path',
        'youtube_connected_at',
        'youtube_last_checked_at',
        'youtube_last_prepare_status',
        'youtube_last_prepare_message',
        'last_playlist_id',
        'last_video_ids',
        'is_active'
    ];

    protected $casts = [
        'last_video_ids' => 'array',
        'is_active' => 'boolean',
        'google_oauth_connected_at' => 'datetime',
        'youtube_connected_at' => 'datetime',
        'youtube_last_checked_at' => 'datetime',
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
