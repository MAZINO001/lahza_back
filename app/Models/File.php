<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    protected $fillable = ['path', 'type', 'user_id', 'fileable_id', 'fileable_type'];
    protected $appends = ['url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fileable()
    {
        return $this->morphTo();
    }

    public function getUrlAttribute()
    {
        return Storage::url($this->path);
    }
}
