<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        "service_id",
        "title",
        "description",
        "discount_type",
        "discount_value",
        "start_date",
        "end_date",
        "status",
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
       public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
