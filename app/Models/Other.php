<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;

class Other extends Model
{
    use LogsActivity;
    protected $fillable = [
        'user_id',
        'description',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];
     public function user()
    {
        return $this->belongsTo(User::class);
    }
}
