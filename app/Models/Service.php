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
        "status",
        'time'
    ];


public function quotes()
{
    return $this->belongsToMany(Quotes::class, 'quotes_services', 'service_id', 'quote_id')
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
  public function invoices()
{
    return $this->belongsToMany(
        Invoice::class,
        'invoice_services',
        'service_id',
        'invoice_id'
    )->withPivot(['quantity', 'tax', 'individual_total'])
     ->withTimestamps();
}

}
