<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;
class Intern extends Model
{
    use LogsActivity;
    protected $fillable = [
        'user_id',
        'department',
        'linkedin',
        'github',
        'cv',
        'portfolio',
        'start_date',
        'end_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
       public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
