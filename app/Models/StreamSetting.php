<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StreamSetting extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'youtube_key'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
