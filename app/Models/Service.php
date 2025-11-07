<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'description',
        'base_Price',
    ];

    public function quotes()
    {
        return $this->belongsToMany(Quotes::class, 'quotes_services')
            ->withPivot(['quantity', 'tax', 'individual_total'])
            ->withTimestamps();
    }
}
