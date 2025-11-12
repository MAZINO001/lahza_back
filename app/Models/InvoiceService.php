<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceService extends Model
{
    protected $table = 'invoice_services';

    protected $fillable = [
        'invoice_id',
        'service_id',
        'quantity',
        'tax',
        'individual_total',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
