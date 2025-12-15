<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;

class Service extends Model
{
    use LogsActivity;
    protected $fillable = [
        'name',
        'description',
        'base_price',
        'tax_rate',
        "status"
    ];


    public function quotes()
    {
        return $this->belongsToMany(Quotes::class, 'quotes_services')
            ->withPivot(['quantity', 'tax', 'individual_total'])
            ->withTimestamps();
    }
    public function offers()
    {
        return $this->hasMany(Offer::class);
    }
       public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
