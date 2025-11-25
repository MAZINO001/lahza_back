<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;

class permission extends Model
{
    use LogsActivity;
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
