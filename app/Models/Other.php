<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Other extends Model
{
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
