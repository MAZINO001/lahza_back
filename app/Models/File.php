<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Traits\LogsActivity;

class File extends Model
{
    use LogsActivity;
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
        return Storage::disk('public')->url($this->path);
    }
    public function getBase64Attribute()
    {
        try {
            if (Storage::disk('public')->exists($this->path)) {
                $imageData = Storage::disk('public')->get($this->path);
                $mimeType = Storage::disk('public')->mimeType($this->path);
                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            }
        } catch (\Exception $e) {
            Log::error('Error getting base64: ' . $e->getMessage());
        }

        return null;
    }
       public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
