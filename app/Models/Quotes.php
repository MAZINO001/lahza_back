<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotes extends Model
{
    protected $fillable = [
        'client_id',
        'quotation_date',
        'status',
        'notes',
        'total_amount',
    ];

    // 🔗 Relationships
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'quotes_services')
            ->withPivot(['quantity', 'tax', 'individual_total'])
            ->withTimestamps();
    }

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
    public function quoteServices()
    {
        return $this->hasMany(Quotes_service::class, 'quote_id');
    }
}
