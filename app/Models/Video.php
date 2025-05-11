<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'path', 'user_id', 'order'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($video) {
            if (is_null($video->order)) {
                $video->order = Video::where('user_id', $video->user_id)->max('order') + 1;
            }
        });
    }
}
