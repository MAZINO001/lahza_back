<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;

class userPermission extends Model
{
    use LogsActivity;
    protected $fillable = [
        'user_id',
        'permission_id',
    ];
}
