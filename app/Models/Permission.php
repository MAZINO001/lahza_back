<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class permission extends Model
{
    protected $fillable = [
        'name',
        'key',
        'description',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions');
    }
}
