<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;

class organization extends Model
{
    use LogsActivity;
    protected $fillable = [
        'name',
        'contact_email',
        'phone',
        'address',
        'website',
        'description',
    ];
}
