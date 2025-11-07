<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class userPermission extends Model
{
    protected $fillable = [
        'user_id',
        'permission_id',
    ];
}
