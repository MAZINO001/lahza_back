<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;

class Quotes_service extends Model
{
    use LogsActivity;
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
