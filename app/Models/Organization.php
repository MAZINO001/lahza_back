<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class organization extends Model
{
    protected $fillable = [
        'name',
        'contact_email',
        'phone',
        'address',
        'website',
        'description',
    ];
}
