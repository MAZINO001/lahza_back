<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotes_service extends Model
{
    protected $table = 'quotes_services';

    protected $fillable = [
        'quote_id',
        'service_id',
        'quantity',
        'tax',
        'individual_total',
    ];

    public function quote()
    {
        return $this->belongsTo(Quotes::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
